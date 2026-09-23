<?php

declare(strict_types=1);

namespace QBuilder;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Builder\RecursiveCteBuilder;
use QBuilder\Builder\UnionBuilder;
use QBuilder\Condition\ConditionBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Drivers\DriverFactory;
use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\Services\SqlSecurity;

/**
 * SQL query builder with method chaining support.
 *
 * Main class for building SQL queries with fluent interface.
 * Each method returns $this for method chaining.
 * Calling new select|insert|update|delete|procedure() clears old query buffer.
 *
 * @example
 * $qb = new QueryBuilder();
 *
 * // Build SELECT query
 * $sql = $qb->select('id', 'name')->from('users')->where()->eq('status', 1)->limit(1, 4)->getQuery();
 *
 * // Build INSERT query
 * $qb->insert('users')->insertRow(['name' => 'John', 'email' => 'john@info.com'])->getQuery();
 *
 * // Build UPDATE query
 * $qb->update('users')->updateRow(['status' => 'active'])->where()->eq('id', 123)->getQuery();
 *
 * // Build DELETE query
 * $qb->delete('users')->where()->eq('id', 123)->getQuery();
 *
 * // Build PROCEDURE query
 * $qb->procedure('GetAllUsers')->getQuery();
 */
class QueryBuilder
{
    protected string $driver = QbConsts::DRIVER_PDO_MYSQL;
    protected ?DriverInterface $driverInstance = null;

    protected string $serverVersion = '';
    protected string $type = '';
    protected bool $distinctEnabled = false;

    /**
     * @var array<int,array{
     *     field:string,
     *     table:string,
     *     alias:string,
     *     isExpression:?bool,
     *     isSubquery:?bool,
     *     subqueryObj:?QueryBuilder
     * }>
     */
    protected array $selectFields = [];
    protected string $fromTable = '';
    protected string $fromAlias = '';
    protected bool $fromIsSubquery = false;
    protected bool $fromFinal = false;

    /** @var list<array{type:string,indexes:list<string>,for:string}> */
    protected array $fromIndexHints = [];

    /** @var array<int,list<array{type:string,indexes:list<string>,for:string}>> */
    protected array $joinIndexHints = [];

    /**
     * Table the next index hint applies to: 'from', join position, 'subquery', or null before from().
     */
    protected int|string|null $indexHintTarget = null;

    /**
     * @var array<int,array{
     *     type:string,
     *     table:string,
     *     alias:string,
     *     conditions:ConditionJoin
     * }>
     */
    protected array $joinClauses = [];

    /** @var array<int,array{type:string,subQuery:QueryBuilder,alias:string,conditions:ConditionJoin}> */
    protected array $joinFromSelectClauses = [];
    protected ?ConditionBuilder $whereBuilder = null;

    /**
     * @var array<int,array{
     *     field:string,
     *     table:string,
     *     alias:string,
     *     isExpression:?bool,
     *     isSubquery:?bool,
     *     subqueryObj:?QueryBuilder
     * }>
     */
    protected array $groupBy = [];
    protected ?ConditionBuilder $havingBuilder = null;

    /** @var array<int,array{field:string,table:string,direction:string}> */
    protected array $orderBy = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected bool $limitWithTies = false;

    /** @var array<string,array<int,string>|QueryBuilder|string> */
    protected array $insertData = [];

    /** @var array<int,array<string,?scalar>> */
    protected array $insertRows = [];

    /** @var array<int,string> */
    protected array $insertFields = [];
    protected string $insertConflictData = '';

    /** @var array<string,?scalar> */
    protected array $updateData = [];

    /** @var array<int,QueryBuilder> */
    protected array $unionQueries = [];
    protected bool $unionAll = false;

    protected string $procedureName = '';

    /** @var array<int,?scalar> */
    protected array $procedureParams = [];

    /**
     * @param string $driver Database driver (by default mysql)
     *
     * @throws UnsupportedFeatureException If driver is not supported
     */
    public function __construct(string $driver = QbConsts::DRIVER_PDO_MYSQL)
    {
        $this->setDriver($driver);
    }

    /**
     * Set database driver.
     *
     * @param string $driver Database driver
     *
     * @throws UnsupportedFeatureException If driver is not supported
     *
     * @example
     * $qb->setDriver(QbConsts::DRIVER_POSTGRESQL);
     */
    public function setDriver(string $driver): self
    {
        $supportedDrivers = QbConsts::getSupportedDrivers();

        if (! \in_array($driver, $supportedDrivers, true)) {
            throw new UnsupportedFeatureException(
                "Unsupported database driver: '{$driver}'. Supported drivers: "
                    .implode(', ', $supportedDrivers)
            );
        }

        $this->driver = $driver;
        $this->driverInstance = null;

        return $this;
    }

    /**
     * Get current database driver.
     *
     * @example
     * $driver = $qb->getDriver(); // 'mysql'
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Get driver instance.
     *
     * @throws UnsupportedFeatureException If driver is not supported
     */
    public function getDriverInstance(): DriverInterface
    {
        if (! $this->driverInstance instanceof DriverInterface) {
            $this->driverInstance = DriverFactory::create($this->driver)->setServerVersion($this->serverVersion);
        }

        return $this->driverInstance;
    }

    /**
     * Set database server version to choose version-dependent syntax.
     *
     * Take it from the connection, e.g. PDO::ATTR_SERVER_VERSION. Subqueries inherit it.
     * MySQL 8.0.19+ uses INSERT ... AS `new` row alias in ON DUPLICATE KEY UPDATE instead of deprecated VALUES().
     *
     * @param string $version Server version string, empty string for unknown
     *
     * @example
     * $qb->setServerVersion($pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
     */
    public function setServerVersion(string $version): self
    {
        $this->serverVersion = $version;
        $this->driverInstance?->setServerVersion($version);

        return $this;
    }

    /**
     * Get database server version, or empty string when unknown.
     */
    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    /**
     * Create new QueryBuilder instance for subqueries.
     * Uses same driver.
     *
     * @example
     * $subQuery = $qb->subQuery()
     *     ->select('user_id')
     *     ->from('orders')
     *     ->where()->eq('status', 'active')->end();
     */
    public function subQuery(): self
    {
        return (new self($this->driver))->setServerVersion($this->serverVersion);
    }

    /**
     * Start SELECT query.
     * Clears previous query buffer.
     *
     * @param Field|string ...$fields Fields to select. Can be:
     *                                - string: 'id', 'name', 'email'
     *                                - Field object for field with table and/or alias
     *
     * @example
     * ->select('id', 'name', Field::set('COUNT(*)', '', 'cnt'))
     */
    public function select(Field|string ...$fields): self
    {
        $this->reset();
        $this->type = 'SELECT';

        if ([] === $fields) {
            $fields = ['*'];
        }

        $this->selectFields = $this->normalizeFields(array_values($fields));

        return $this;
    }

    /**
     * Enable DISTINCT for SELECT query.
     * Must be called after select().
     *
     * @example
     * ->select('user_id')->distinct()->from('orders')
     */
    public function distinct(): self
    {
        $this->distinctEnabled = true;

        return $this;
    }

    /**
     * Specify table or subquery for SELECT.
     *
     * @param QueryBuilder|string $table Table name or QueryBuilder for subquery
     * @param string              $alias Table alias (required for subqueries)
     *
     * @example
     * // Regular table
     * ->from('users', 'u')
     *
     * // Subquery
     * $subQuery = $qb->subQuery()->select('id')->from('temp_users');
     * ->from($subQuery, 'tu')
     */
    public function from(self|string $table, string $alias = ''): self
    {
        if ($table instanceof self) {
            $this->fromTable = '('.$table->build().')';
            $this->fromAlias = '' === $alias || '0' === $alias
                ? ''
                : SqlSecurity::validateAliasName($alias, $this->driver);
            $this->fromIsSubquery = true;
            $this->indexHintTarget = 'subquery';
        } else {
            $this->fromTable = SqlSecurity::validateTableName($table, $this->driver);
            $this->fromAlias = '' === $alias || '0' === $alias
                ? ''
                : SqlSecurity::validateAliasName($alias, $this->driver);
            $this->fromIsSubquery = false;
            $this->indexHintTarget = 'from';
        }

        return $this;
    }

    /**
     * Enable FINAL modifier for FROM clause (ClickHouse only).
     * Forces on-the-fly merge of data parts to get deduplicated/actual data.
     * Ignored on non-ClickHouse drivers.
     *
     * @example
     * // ClickHouse: SELECT * FROM telemetry.sensor_data FINAL
     * ->select()->from('telemetry.sensor_data')->final()
     *
     * // Other drivers: SELECT * FROM users (FINAL is ignored)
     * ->select()->from('users')->final()
     */
    public function final(): self
    {
        $this->fromFinal = true;

        return $this;
    }

    /**
     * Add USE INDEX hint to the table added last: FROM table or the last JOIN.
     *
     * MySQL/MariaDB: USE INDEX. MS SQL Server: not supported (throws on build).
     * Other drivers ignore index hints, as they do not change the query result.
     * An empty list gives USE INDEX (), which tells MySQL to use no indexes.
     *
     * @param array<int,string>|string $indexes Index name or names
     * @param string                   $for     Hint scope: '' or QbConsts::INDEX_FOR_* constant
     *
     * @throws InvalidQueryException If there is no table to apply the hint to, or the hint is invalid
     *
     * @example
     * ->from('orders', 'o')->useIndex(['idx_status', 'idx_created'])
     */
    public function useIndex(array|string $indexes, string $for = ''): self
    {
        return $this->addIndexHint(QbConsts::INDEX_USE, $indexes, $for);
    }

    /**
     * Add FORCE INDEX hint to the table added last: FROM table or the last JOIN.
     *
     * MySQL/MariaDB: FORCE INDEX. MS SQL Server: WITH (INDEX(...)), without scope.
     * Other drivers ignore index hints, as they do not change the query result.
     *
     * @param array<int,string>|string $indexes Index name or names
     * @param string                   $for     Hint scope: '' or QbConsts::INDEX_FOR_* constant
     *
     * @throws InvalidQueryException If there is no table to apply the hint to, or the hint is invalid
     *
     * @example
     * ->from('orders', 'o')->forceIndex('idx_created', QbConsts::INDEX_FOR_ORDER_BY)
     */
    public function forceIndex(array|string $indexes, string $for = ''): self
    {
        return $this->addIndexHint(QbConsts::INDEX_FORCE, $indexes, $for);
    }

    /**
     * Add IGNORE INDEX hint to the table added last: FROM table or the last JOIN.
     *
     * MySQL/MariaDB: IGNORE INDEX. MS SQL Server: not supported (throws on build).
     * Other drivers ignore index hints, as they do not change the query result.
     *
     * @param array<int,string>|string $indexes Index name or names
     * @param string                   $for     Hint scope: '' or QbConsts::INDEX_FOR_* constant
     *
     * @throws InvalidQueryException If there is no table to apply the hint to, or the hint is invalid
     *
     * @example
     * ->leftJoin('users', 'u', $condition)->ignoreIndex('idx_email')
     */
    public function ignoreIndex(array|string $indexes, string $for = ''): self
    {
        return $this->addIndexHint(QbConsts::INDEX_IGNORE, $indexes, $for);
    }

    /**
     * @param string        $table      Table for JOIN
     * @param string        $alias      Table alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     * @param string        $type       JOIN type: use JOIN_* constants
     *
     * @example
     * ->join('orders', 'o', ConditionJoin::create($db, 'user_id', 'id', 'users'))
     */
    public function join(
        string $table,
        string $alias,
        ConditionJoin $conditions,
        string $type = QbConsts::JOIN_INNER
    ): self {
        $validTypes = [
            QbConsts::JOIN_INNER,
            QbConsts::JOIN_LEFT,
            QbConsts::JOIN_RIGHT,
            QbConsts::JOIN_FULL,
            QbConsts::JOIN_CROSS,
        ];

        $typeUpper = strtoupper($type);

        if (! \in_array($typeUpper, $validTypes, true)) {
            $typeUpper = QbConsts::JOIN_INNER;
        }

        $this->joinClauses[] = [
            'type' => $typeUpper,
            'table' => SqlSecurity::validateTableName($table, $this->driver),
            'alias' => $alias ? SqlSecurity::validateAliasName($alias, $this->driver) : '',
            'conditions' => $conditions,
        ];
        $this->indexHintTarget = array_key_last($this->joinClauses);

        return $this;
    }

    /**
     * Add LEFT JOIN.
     *
     * @param string        $table      Table for JOIN
     * @param string        $alias      Table alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * ->leftJoin('orders', 'o', ConditionJoin::create($db, 'user_id', 'id', 'users'))
     */
    public function leftJoin(string $table, string $alias, ConditionJoin $conditions): self
    {
        return $this->join($table, $alias, $conditions, QbConsts::JOIN_LEFT);
    }

    /**
     * Add RIGHT JOIN.
     *
     * @param string        $table      Table for JOIN
     * @param string        $alias      Table alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * ->rightJoin('orders', 'o', ConditionJoin::create($db, 'user_id', 'id', 'users'))
     */
    public function rightJoin(string $table, string $alias, ConditionJoin $conditions): self
    {
        return $this->join($table, $alias, $conditions, QbConsts::JOIN_RIGHT);
    }

    /**
     * Add INNER JOIN.
     *
     * @param string        $table      Table for JOIN
     * @param string        $alias      Table alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * ->innerJoin('orders', 'o', ConditionJoin::create($db, 'user_id', 'id', 'users'))
     */
    public function innerJoin(string $table, string $alias, ConditionJoin $conditions): self
    {
        return $this->join($table, $alias, $conditions, QbConsts::JOIN_INNER);
    }

    /**
     * Add FULL JOIN.
     *
     * @param string        $table      Table for JOIN
     * @param string        $alias      Table alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * ->fullJoin('orders', 'o', ConditionJoin::create($db, 'user_id', 'id', 'users'))
     */
    public function fullJoin(string $table, string $alias, ConditionJoin $conditions): self
    {
        return $this->join($table, $alias, $conditions, QbConsts::JOIN_FULL);
    }

    /**
     * Add CROSS JOIN.
     * CROSS JOIN does not require conditions, but ConditionJoin object is passed for consistency.
     *
     * @param string             $table      Table for JOIN
     * @param string             $alias      Table alias
     * @param null|ConditionJoin $conditions Object with JOIN conditions (optional for CROSS JOIN)
     *
     * @example
     * ->crossJoin('products', 'p', ConditionJoin::create($this))
     */
    public function crossJoin(
        string $table,
        string $alias,
        ?ConditionJoin $conditions = null
    ): self {
        if (! $conditions instanceof ConditionJoin) {
            $conditions = ConditionJoin::create($this);
        }

        return $this->join($table, $alias, $conditions, QbConsts::JOIN_CROSS);
    }

    /**
     * Add LEFT JOIN with subquery.
     *
     * @param QueryBuilder  $subQuery   Subquery for JOIN
     * @param string        $alias      Subquery alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * $subQuery = (new QueryBuilder())
     *     ->select('*')
     *     ->from('orders')
     *     ->where()->eq('status', 'active')->end();
     * ->leftJoinFromSelect($subQuery, 'o', ConditionJoin::create($qb, 'user_id', 'id', 'users'))
     */
    public function leftJoinFromSelect(
        self $subQuery,
        string $alias,
        ConditionJoin $conditions
    ): self {
        $this->joinFromSelectClauses[] = [
            'type' => QbConsts::JOIN_LEFT,
            'subQuery' => $subQuery,
            'alias' => SqlSecurity::validateAliasName($alias, $this->driver),
            'conditions' => $conditions,
        ];
        $this->indexHintTarget = 'subquery';

        return $this;
    }

    /**
     * Add INNER JOIN with subquery.
     *
     * @param QueryBuilder  $subQuery   Subquery for JOIN
     * @param string        $alias      Subquery alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * $subQuery = (new QueryBuilder())->select('*')->from('orders');
     * ->innerJoinFromSelect($subQuery, 'o', ConditionJoin::create($qb, 'user_id', 'id', 'users'))
     */
    public function innerJoinFromSelect(
        self $subQuery,
        string $alias,
        ConditionJoin $conditions
    ): self {
        $this->joinFromSelectClauses[] = [
            'type' => QbConsts::JOIN_INNER,
            'subQuery' => $subQuery,
            'alias' => SqlSecurity::validateAliasName($alias, $this->driver),
            'conditions' => $conditions,
        ];

        return $this;
    }

    /**
     * Add RIGHT JOIN with subquery.
     *
     * @param QueryBuilder  $subQuery   Subquery for JOIN
     * @param string        $alias      Subquery alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * $subQuery = (new QueryBuilder())->select('*')->from('orders');
     * ->rightJoinFromSelect($subQuery, 'o', ConditionJoin::create($qb, 'user_id', 'id', 'users'))
     */
    public function rightJoinFromSelect(
        self $subQuery,
        string $alias,
        ConditionJoin $conditions
    ): self {
        $this->joinFromSelectClauses[] = [
            'type' => QbConsts::JOIN_RIGHT,
            'subQuery' => $subQuery,
            'alias' => SqlSecurity::validateAliasName($alias, $this->driver),
            'conditions' => $conditions,
        ];

        return $this;
    }

    /**
     * Add FULL JOIN with subquery.
     *
     * @param QueryBuilder  $subQuery   Subquery for JOIN
     * @param string        $alias      Subquery alias
     * @param ConditionJoin $conditions Object with JOIN conditions
     *
     * @example
     * $subQuery = (new QueryBuilder())->select('*')->from('orders');
     * ->fullJoinFromSelect($subQuery, 'o', ConditionJoin::create($qb, 'user_id', 'id', 'users'))
     */
    public function fullJoinFromSelect(
        self $subQuery,
        string $alias,
        ConditionJoin $conditions
    ): self {
        $this->joinFromSelectClauses[] = [
            'type' => QbConsts::JOIN_FULL,
            'subQuery' => $subQuery,
            'alias' => SqlSecurity::validateAliasName($alias, $this->driver),
            'conditions' => $conditions,
        ];

        return $this;
    }

    /**
     * Add CROSS JOIN with subquery.
     *
     * @param QueryBuilder       $subQuery   Subquery for JOIN
     * @param string             $alias      Subquery alias
     * @param null|ConditionJoin $conditions Object with JOIN conditions (optional for CROSS JOIN)
     *
     * @example
     * $subQuery = (new QueryBuilder())->select('*')->from('products');
     * ->crossJoinFromSelect($subQuery, 'p')
     */
    public function crossJoinFromSelect(
        self $subQuery,
        string $alias,
        ?ConditionJoin $conditions = null
    ): self {
        if (! $conditions instanceof ConditionJoin) {
            $conditions = ConditionJoin::create($this);
        }

        $this->joinFromSelectClauses[] = [
            'type' => QbConsts::JOIN_CROSS,
            'subQuery' => $subQuery,
            'alias' => SqlSecurity::validateAliasName($alias, $this->driver),
            'conditions' => $conditions,
        ];

        return $this;
    }

    /**
     * Get object for building WHERE conditions.
     * Creates ConditionBuilder instance on first access.
     *
     * @param null|callable(ConditionBuilder): ConditionBuilder $closure Optional closure for building conditions
     *
     * @return ($closure is null ? ConditionBuilder : self)
     *
     * @example
     * // Standard syntax
     * ->where()
     *     ->eq('status', 1)
     *     ->and()
     *     ->in('role', ['admin', 'user'])
     *     ->end()
     *
     * // Alternative syntax with closure
     * ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q
     *     ->eq('status', 1)
     *     ->and()
     *     ->in('role', ['admin', 'user']))
     */
    public function where(?callable $closure = null): ConditionBuilder|self
    {
        if (! $this->whereBuilder instanceof ConditionBuilder) {
            $this->whereBuilder = new ConditionBuilder($this, 'WHERE');
        }

        if (null !== $closure) {
            $closure($this->whereBuilder);

            return $this;
        }

        return $this->whereBuilder;
    }

    /**
     * Get object for building HAVING conditions.
     * Creates ConditionBuilder instance on first access.
     *
     * @param null|callable(ConditionBuilder): ConditionBuilder $closure Optional closure for building conditions
     *
     * @return ($closure is null ? ConditionBuilder : self)
     *
     * @example
     * // Standard syntax
     * ->groupBy(ConditionBy::groupBy()->add('user_id'))
     * ->having()
     *     ->gt('COUNT(*)', 5)
     *     ->end()
     *
     * // Alternative syntax with closure
     * ->having(static fn (ConditionBuilder $q): ConditionBuilder => $q->gt('COUNT(*)', 5))
     */
    public function having(?callable $closure = null): ConditionBuilder|self
    {
        if (! $this->havingBuilder instanceof ConditionBuilder) {
            $this->havingBuilder = new ConditionBuilder($this, 'HAVING');
        }

        if (null !== $closure) {
            $closure($this->havingBuilder);

            return $this;
        }

        return $this->havingBuilder;
    }

    /**
     * Add GROUP BY.
     * Accepts ConditionBy object.
     *
     * @param ConditionBy $groupBy Object with fields for grouping
     *
     * @example
     * ->groupBy(ConditionBy::groupBy()->add('user_id')->add('status', 'orders'))
     */
    public function groupBy(ConditionBy $groupBy): self
    {
        foreach ($groupBy->getFields() as $field) {
            $this->groupBy[] = [
                'field' => $field->name,
                'table' => $field->tableOrAlias,
                'alias' => '',
                'isExpression' => false,
                'isSubquery' => false,
                'subqueryObj' => null,
            ];
        }

        return $this;
    }

    /**
     * Add ORDER BY.
     * Accepts ConditionBy object.
     *
     * @param ConditionBy $orderBy Object with fields for sorting
     *
     * @example
     * ->orderBy(ConditionBy::orderBy()->asc('name')->desc('created_at', 'u'))
     */
    public function orderBy(ConditionBy $orderBy): self
    {
        foreach ($orderBy->getItems() as $item) {
            $this->orderBy[] = [
                'field' => $item['field']->name,
                'table' => $item['field']->tableOrAlias,
                'direction' => $item['direction'] ?? QbConsts::ORDER_ASC,
                'isExpression' => false,
                'isSubquery' => false,
                'subqueryObj' => null,
            ];
        }

        return $this;
    }

    /**
     * Set LIMIT and OFFSET.
     * If limit is 0 or negative, limit is not applied.
     *
     * @param int $limit  Number of records (must be > 0 to apply)
     * @param int $offset Offset (default 0, must be >= 0)
     *
     * @example
     * // First 10 records
     * ->limit(10)
     *
     * // 10 records starting from 20th
     * ->limit(10, 20)
     */
    public function limit(int $limit = 0, int $offset = 0): self
    {
        if ($limit <= 0) {
            $this->limitValue = null;
            $this->offsetValue = null;
        } else {
            $this->limitValue = $limit;
            $this->offsetValue = $offset > 0 ? $offset : null;
        }

        $this->limitWithTies = false;

        return $this;
    }

    /**
     * Set LIMIT with WITH TIES clause.
     * Returns additional rows that have the same values in ORDER BY columns as the last selected row.
     * Requires ORDER BY clause.
     *
     * Supported drivers:
     * - ClickHouse: LIMIT n WITH TIES
     * - PostgreSQL: FETCH FIRST n ROWS WITH TIES
     * - MS SQL Server: TOP n WITH TIES
     * - Oracle: FETCH FIRST n ROWS WITH TIES
     *
     * Not supported:
     * - MySQL: throws exception
     * - SQLite: throws exception
     *
     * @param int $limit Number of records
     *
     * @throws UnsupportedFeatureException If driver does not support WITH TIES
     *
     * @example
     * // Get top 5 salaries with all employees having the same salary as the 5th
     * ->select('name', 'salary')
     * ->from('employees')
     * ->orderBy(ConditionBy::orderBy()->add('salary', '', 'DESC'))
     * ->limitWithTies(5)
     */
    public function limitWithTies(int $limit): self
    {
        if ($limit <= 0) {
            $this->limitValue = null;
            $this->limitWithTies = false;
        } else {
            $this->limitValue = $limit;
            $this->limitWithTies = true;
            $this->offsetValue = null;
        }

        return $this;
    }

    /**
     * Start INSERT query.
     * Clears previous query buffer.
     *
     * @param string $table Table name
     *
     * @example
     * ->insert('users')
     *     ->insertRow(['name' => 'John', 'email' => 'john@example.com'])
     *     ->insertRow(['name' => 'Jane', 'email' => 'jane@example.com'])
     */
    public function insert(string $table): self
    {
        $this->reset();
        $this->type = 'INSERT';
        $this->fromTable = SqlSecurity::validateTableName($table, $this->driver);

        return $this;
    }

    /**
     * INSERT from subquery.
     *
     * @param QueryBuilder       $subQuery Subquery SELECT
     * @param array<int, string> $fields   Field list for insert (optional)
     *
     * @example
     * $subQuery = $qb->subQuery()
     *     ->select('name', 'email')
     *     ->from('temp_users')
     *     ->where()->eq('verified', 1)->end();
     * ->insert('users')->insertFrom($subQuery, ['name', 'email'])
     */
    public function insertFrom(self $subQuery, array $fields = []): self
    {
        $this->insertData = [
            '_subquery' => $subQuery,
            '_fields' => $fields,
        ];

        return $this;
    }

    /**
     * Add data row for INSERT (cumulative effect).
     * Supports multiple row insertion.
     *
     * @param array<string,?scalar> $data Data to insert. Format: ['field' => 'value', ...]
     *
     * @example
     * ->insertRow(['name' => 'John', 'age' => 30])
     * ->insertRow(['name' => 'Jane', 'age' => 25])
     */
    public function insertRow(array $data): self
    {
        if ([] === $this->insertRows) {
            $this->insertFields = array_keys($data);
        }

        $this->insertRows[] = $data;

        return $this;
    }

    /**
     * Create INSERT conflict handler builder.
     * Automatically selects correct implementation for current driver.
     *
     * @throws UnsupportedFeatureException If driver does not support conflict handling
     *
     * @example
     * // Universal approach - works for any driver
     * $conflictBuilder = $qb->conflictBuilder()
     *     ->set('name', $userName)
     *     ->increment('view_count')
     *     ->sqlFunction('updated_at', 'NOW()');
     *
     * // For PostgreSQL additionally need to specify conflictTarget
     * if ($qb->getDriver() === QbConsts::DRIVER_POSTGRESQL) {
     *     $conflictBuilder->conflictTarget(['email']);
     * }
     *
     * $qb->insert('users', $data)->insertConflictHandler($conflictBuilder);
     */
    public function conflictBuilder(): ConflictBuilderInterface
    {
        return $this->getDriverInstance()->createConflictBuilder($this);
    }

    /**
     * Add ON DUPLICATE KEY UPDATE (MySQL) / ON CONFLICT (PostgreSQL) to INSERT query.
     * Safest and most flexible way for complex UPDATE expressions.
     *
     * @param ConflictBuilderInterface $builder UPDATE expression builder
     *
     * @example
     * // Simple universal approach
     * $qb->insert('users', $data)
     *    ->insertConflictHandler(
     *        $qb->conflictBuilder()
     *           ->set('name', $userName)
     *           ->increment('view_count')
     *    );
     */
    public function insertConflictHandler(ConflictBuilderInterface $builder): self
    {
        $this->insertConflictData = $builder->build();

        return $this;
    }

    /**
     * Start UPDATE query.
     * Clears previous query buffer.
     *
     * @param string $table Table name
     *
     * @example
     * ->update('users')
     *     ->updateRow(['status' => 'active'])
     *     ->where()->eq('id', 123)->end()
     */
    public function update(string $table): self
    {
        $this->reset();
        $this->type = 'UPDATE';
        $this->fromTable = SqlSecurity::validateTableName($table, $this->driver);

        return $this;
    }

    /**
     * Set values for UPDATE.
     *
     * @param array<string,?scalar> $data Data to update. Format: ['field' => 'value', ...]
     *
     * @example
     * ->updateRow(['status' => 'active', 'updated_at' => '2024-01-01'])
     */
    public function updateRow(array $data): self
    {
        $this->updateData = array_merge($this->updateData, $data);

        return $this;
    }

    /**
     * UPDATE using subquery to determine records to update.
     * Updates records in table whose IDs are in subquery result.
     *
     * @param string                $table    Table name
     * @param QueryBuilder          $subQuery Subquery SELECT
     * @param array<string,?scalar> $data     Data to update
     * @param string                $idField  ID field for matching (by default 'id')
     *
     * @example
     * $subQuery = $qb->subQuery()->select('user_id')->from('temp_users')->where()->eq('status', 1);
     * $qb->updateFromSelect('users', $subQuery, ['status' => 'verified'], 'id')
     */
    public function updateFromSelect(
        string $table,
        self $subQuery,
        array $data,
        string $idField = 'id'
    ): self {
        $this->reset();
        $this->type = 'UPDATE';
        $this->fromTable = SqlSecurity::validateTableName($table, $this->driver);
        $this->updateData = $data;

        $this->insertData = [
            '_update_subquery' => $subQuery,
            '_update_id_field' => SqlSecurity::validateFieldName($idField, $this->driver),
        ];

        return $this;
    }

    /**
     * Start DELETE query.
     * Clears previous query buffer.
     *
     * @param string $table Table name
     *
     * @example
     * ->delete('users')
     *     ->where()->lt('last_login', '2020-01-01')->end()
     */
    public function delete(string $table = ''): self
    {
        $this->reset();
        $this->type = 'DELETE';

        if ('' !== $table && '0' !== $table) {
            $this->fromTable = SqlSecurity::validateTableName($table, $this->driver);
        }

        return $this;
    }

    /**
     * DELETE using subquery to determine records to delete.
     * Deletes records from table whose IDs are in subquery result.
     *
     * @param string       $table    Table name
     * @param QueryBuilder $subQuery Subquery SELECT
     * @param string       $idField  ID field for matching (by default 'id')
     *
     * @example
     * $subQuery = $qb->subQuery()->select('user_id')->from('temp_users')->where()->eq('status', 'deleted');
     * $qb->deleteFromSelect('users', $subQuery, 'id')
     */
    public function deleteFromSelect(string $table, self $subQuery, string $idField = 'id'): self
    {
        $this->reset();
        $this->type = 'DELETE';
        $this->fromTable = SqlSecurity::validateTableName($table, $this->driver);

        $this->insertData = [
            '_delete_subquery' => $subQuery,
            '_delete_id_field' => SqlSecurity::validateFieldName($idField, $this->driver),
        ];

        return $this;
    }

    /**
     * Create UnionBuilder object for building UNION queries.
     *
     * @example
     * $union = $qb->union();
     * $union->add($qb->select('id', 'name')->from('users'));
     * $union->add($qb->select('id', 'name')->from('admins'));
     * $sql = $union->build();
     */
    public function union(): UnionBuilder
    {
        return new UnionBuilder($this);
    }

    /**
     * Create recursive CTE query builder.
     *
     * @param string $cteName CTE table name
     *
     * @example
     * $cte = $qb->recursiveCte('AllAncestors')
     *     ->baseQuery(...)
     *     ->recursiveQuery(...)
     *     ->finalSelect(...);
     * $sql = $cte->build();
     */
    public function recursiveCte(string $cteName = 'RecursiveCTE'): RecursiveCteBuilder
    {
        return new RecursiveCteBuilder($this, $cteName);
    }

    /**
     * Build SQL query (internal method, delegates to driver SqlBuilder).
     *
     * @param bool $compact Remove extra newlines and spaces
     *
     * @return string Ready SQL query
     */
    public function build(bool $compact = false): string
    {
        return $this->getDriverInstance()->getSqlBuilder($this)->build($compact);
    }

    /**
     * Call stored procedure.
     * Clears previous query buffer.
     *
     * Other DBMS:
     * - MySQL: CALL procedure_name(params)
     * - PostgreSQL: CALL procedure_name(params) or SELECT * FROM procedure_name(params)
     * - SQL Server: EXEC procedure_name params (without parentheses and commas) or EXECUTE
     * - Oracle: BEGIN procedure_name(params); END; or CALL (Oracle 11g+)
     * - SQLite: does not support stored procedures.
     *
     * @param string             $procedureName Procedure name
     * @param array<int,?scalar> $params        Procedure parameters
     *
     * @example
     * // Procedure without parameters
     * $sql = $qb->procedure('GetAllUsers')->build();
     *
     * // Procedure with parameters
     * $sql = $qb->procedure('UpdateUserStatus', [123, 'active'])->build();
     *
     * // Procedure with named parameters (order matters!)
     * $sql = $qb->procedure('CreateReport', [
     *     $userId,
     *     $startDate,
     *     $endDate
     * ])->build();
     */
    public function procedure(string $procedureName, array $params = []): self
    {
        $this->reset();
        $this->type = 'PROCEDURE';
        $this->procedureName = SqlSecurity::validateTableName($procedureName, $this->driver);
        $this->procedureParams = $params;

        return $this;
    }

    /**
     * Get ready query.
     * If absent, runs builder with parameter to clean extra newlines and spaces.
     *
     * @param bool $compact Remove extra newlines and spaces (by default false)
     *
     * @return string Ready SQL query
     *
     * @example
     * $sql = $qb->select('*')->from('users')->getQuery();
     * $compactSql = $qb->getQuery(true);
     */
    public function getQuery(bool $compact = false): string
    {
        return $this->build($compact);
    }

    /**
     * Show query without preparation.
     * If no query - returns empty string.
     *
     * @return string SQL query or empty string
     *
     * @example
     * $qb->select('*')->from('users');
     * echo $qb->showQuery(); // Show current query
     */
    public function showQuery(): string
    {
        return '' === $this->type || '0' === $this->type ? '' : $this->build(false);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDistinctEnabled(): bool
    {
        return $this->distinctEnabled;
    }

    /**
     * @return array<int,array{
     *     field:string,
     *     table:string,
     *     alias:string,
     *     isExpression:?bool,
     *     isSubquery:?bool,
     *     subqueryObj:?QueryBuilder
     * }>
     */
    public function getSelectFields(): array
    {
        return $this->selectFields;
    }

    public function getFromTable(): string
    {
        return $this->fromTable;
    }

    public function getFromAlias(): string
    {
        return $this->fromAlias;
    }

    public function isFromSubquery(): bool
    {
        return $this->fromIsSubquery;
    }

    public function isFromFinal(): bool
    {
        return $this->fromFinal;
    }

    /**
     * @return list<array{type:string,indexes:list<string>,for:string}>
     */
    public function getFromIndexHints(): array
    {
        return $this->fromIndexHints;
    }

    /**
     * @param int $position Join position in getJoinClauses()
     *
     * @return list<array{type:string,indexes:list<string>,for:string}>
     */
    public function getJoinIndexHints(int $position): array
    {
        return $this->joinIndexHints[$position] ?? [];
    }

    /**
     * @return array<int,array{type:string,table:string,alias:string,conditions:ConditionJoin}>
     */
    public function getJoinClauses(): array
    {
        return $this->joinClauses;
    }

    /**
     * @return array<int,array{type:string,subQuery:QueryBuilder,alias:string,conditions:ConditionJoin}>
     */
    public function getJoinFromSelectClauses(): array
    {
        return $this->joinFromSelectClauses;
    }

    public function getWhereBuilder(): ?ConditionBuilder
    {
        return $this->whereBuilder;
    }

    /**
     * @return array<int,array{
     *     field:string,
     *     table:string,
     *     alias:string,
     *     isExpression:?bool,
     *     isSubquery:?bool,
     *     subqueryObj:?QueryBuilder
     * }>
     */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function getHavingBuilder(): ?ConditionBuilder
    {
        return $this->havingBuilder;
    }

    /**
     * @return array<int,array{field:string,table:string,direction:string}>
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimitValue(): ?int
    {
        return $this->limitValue;
    }

    public function getOffsetValue(): ?int
    {
        return $this->offsetValue;
    }

    public function isLimitWithTies(): bool
    {
        return $this->limitWithTies;
    }

    /**
     * @return array<string,array<int,string>|QueryBuilder|string>
     */
    public function getInsertData(): array
    {
        return $this->insertData;
    }

    /**
     * @return array<int,array<string,?scalar>>
     */
    public function getInsertRows(): array
    {
        return $this->insertRows;
    }

    /**
     * @return array<int,string>
     */
    public function getInsertFields(): array
    {
        return $this->insertFields;
    }

    public function getInsertConflictData(): string
    {
        return $this->insertConflictData;
    }

    /**
     * @return array<string,?scalar>
     */
    public function getUpdateData(): array
    {
        return $this->updateData;
    }

    public function getProcedureName(): string
    {
        return $this->procedureName;
    }

    /**
     * @return array<int,?scalar>
     */
    public function getProcedureParams(): array
    {
        return $this->procedureParams;
    }

    /**
     * Validate simple field string.
     *
     * @param string $field Field string to validate
     *
     * @throws InvalidQueryException If field contains expressions
     */
    protected function validateSimpleFieldString(string $field): void
    {
        if ('*' === $field || Field::isIntegerLiteral($field)) {
            return;
        }

        $driver = $this->getDriverInstance();
        $quote = $driver->getIdentifierQuote();
        $closeQuote = $driver->getIdentifierCloseQuote();
        $fieldWithoutQuotes = str_replace([$quote, $closeQuote], '', $field);

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldWithoutQuotes)) {
            throw new InvalidQueryException(
                "Field '{$field}' is invalid. Use Field::set() for expressions or complex field names."
            );
        }
    }

    /**
     * Add index hint to the table added last.
     *
     * @param string                   $type    QbConsts::INDEX_USE, INDEX_FORCE or INDEX_IGNORE
     * @param array<int,string>|string $indexes Index name or names
     * @param string                   $for     Hint scope: '' or QbConsts::INDEX_FOR_* constant
     *
     * @throws InvalidQueryException If there is no table to apply the hint to, or the hint is invalid
     */
    protected function addIndexHint(string $type, array|string $indexes, string $for): self
    {
        if ('SELECT' !== $this->type) {
            throw new InvalidQueryException('Index hints are supported in SELECT queries only');
        }

        if (null === $this->indexHintTarget) {
            throw new InvalidQueryException('Index hint must follow from() or a join');
        }

        if ('subquery' === $this->indexHintTarget) {
            throw new InvalidQueryException('Index hint cannot be applied to a subquery');
        }

        $scopes = ['', QbConsts::INDEX_FOR_JOIN, QbConsts::INDEX_FOR_ORDER_BY, QbConsts::INDEX_FOR_GROUP_BY];

        if (! \in_array($for, $scopes, true)) {
            throw new InvalidQueryException("Invalid index hint scope '{$for}'. Use QbConsts::INDEX_FOR_* constants");
        }

        $indexes = \is_string($indexes) ? [$indexes] : array_values($indexes);

        if ([] === $indexes && QbConsts::INDEX_USE !== $type) {
            throw new InvalidQueryException("{$type} INDEX requires at least one index name");
        }

        foreach ($indexes as $index) {
            SqlSecurity::validateIdentifier($index, 'index', $this->driver);

            if (str_contains($index, '.')) {
                throw new InvalidQueryException("Invalid index name '{$index}': dots are not allowed");
            }
        }

        $joinPosition = \is_int($this->indexHintTarget) ? $this->indexHintTarget : null;
        $hints = null === $joinPosition ? $this->fromIndexHints : $this->joinIndexHints[$joinPosition] ?? [];

        $conflicting = [QbConsts::INDEX_USE => QbConsts::INDEX_FORCE, QbConsts::INDEX_FORCE => QbConsts::INDEX_USE];

        foreach ($hints as $hint) {
            if (isset($conflicting[$type]) && $hint['type'] === $conflicting[$type]) {
                throw new InvalidQueryException('USE INDEX and FORCE INDEX cannot be combined for one table');
            }
        }

        $hints[] = ['type' => $type, 'indexes' => $indexes, 'for' => $for];

        if (null === $joinPosition) {
            $this->fromIndexHints = $hints;
        } else {
            $this->joinIndexHints[$joinPosition] = $hints;
        }

        return $this;
    }

    /**
     * Normalize fields to unified format.
     *
     * @param array<int,Field|string> $fields Field array (strings or Field objects)
     *
     * @return array<int,array{
     *     field:string,
     *     table:string,
     *     alias:string,
     *     isExpression:?bool,
     *     isSubquery:?bool,
     *     subqueryObj:?QueryBuilder
     * }> Normalized array
     */
    protected function normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $value) {
            if ($value instanceof Field) {
                $value->redetectWithDriver($this->getDriver());

                $normalized[] = [
                    'field' => $value->name,
                    'table' => $value->tableOrAlias,
                    'alias' => $value->getFieldAlias(),
                    'isExpression' => $value->isExpression(),
                    'isSubquery' => $value->isSubquery(),
                    'subqueryObj' => $value->isSubquery() ? $value->getSubquery() : null,
                ];
            } else {
                $this->validateSimpleFieldString($value);

                $field = new Field($value);
                $field->redetectWithDriver($this->getDriver());

                $normalized[] = [
                    'field' => $field->name,
                    'table' => $field->tableOrAlias,
                    'alias' => $field->getFieldAlias(),
                    'isExpression' => $field->isExpression(),
                    'isSubquery' => $field->isSubquery(),
                    'subqueryObj' => $field->isSubquery() ? $field->getSubquery() : null,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Clear query buffer.
     * Called when starting new query.
     */
    protected function reset(): void
    {
        $this->type = '';
        $this->distinctEnabled = false;
        $this->selectFields = [];
        $this->fromTable = '';
        $this->fromAlias = '';
        $this->fromIsSubquery = false;
        $this->fromFinal = false;
        $this->fromIndexHints = [];
        $this->joinIndexHints = [];
        $this->indexHintTarget = null;
        $this->joinClauses = [];
        $this->joinFromSelectClauses = [];

        if ($this->whereBuilder instanceof ConditionBuilder) {
            $this->whereBuilder->reset();
        }

        if ($this->havingBuilder instanceof ConditionBuilder) {
            $this->havingBuilder->reset();
        }

        $this->groupBy = [];
        $this->orderBy = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->insertData = [];
        $this->insertRows = [];
        $this->insertFields = [];
        $this->insertConflictData = '';
        $this->updateData = [];
        $this->unionQueries = [];
        $this->unionAll = false;
        $this->procedureName = '';
        $this->procedureParams = [];
    }
}
