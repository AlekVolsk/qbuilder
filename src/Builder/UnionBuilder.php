<?php

namespace QBuilder\Builder;

use QBuilder\Condition\ConditionBy;
use QBuilder\Drivers\DriverInterface;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlCompactor;

/**
 * UNION query builder.
 *
 * Class for building UNION queries with accumulation of multiple SELECT queries.
 * Supports UNION and UNION ALL.
 *
 * @example
 * // Basic UNION
 * $union = $qb->union()
 *     ->add($qb->select('id', 'name')->from('users')->where()->eq('status', 1)->end())
 *     ->add($qb->select('id', 'name')->from('admins')->where()->eq('active', 1)->end())
 *     ->build();
 *
 * // UNION ALL with ORDER BY and LIMIT
 * $union = $qb->union()
 *     ->all()
 *     ->add($qb->select('id', 'name', 'created_at')->from('users'))
 *     ->add($qb->select('id', 'name', 'created_at')->from('admins'))
 *     ->orderBy(ConditionBy::orderBy()->desc('created_at'))
 *     ->limit(100)
 *     ->build();
 */
class UnionBuilder
{
    /** @var array<int,string> */
    protected array $queries = [];
    protected bool $useUnionAll = false;

    /** @var array<int,array{field:string,direction:string}> */
    protected array $orderBy = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;

    protected SqlCompactor $compactor;

    /**
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     */
    public function __construct(protected QueryBuilder $queryBuilder)
    {
        $this->compactor = new SqlCompactor();
    }

    /**
     * Add query to UNION.
     *
     * @param QueryBuilder|string $query Query builder or SQL string
     */
    public function add(QueryBuilder|string $query): self
    {
        if ($query instanceof QueryBuilder) {
            $this->queries[] = $query->build();
        } elseif (\is_string($query)) {
            $this->queries[] = $query;
        }

        return $this;
    }

    /**
     * Use UNION ALL instead of UNION.
     *
     * @param bool $useAll true for UNION ALL, false for UNION
     *
     * @example
     * $union->all()->add($query1)->add($query2)
     */
    public function all(bool $useAll = true): self
    {
        $this->useUnionAll = $useAll;

        return $this;
    }

    /**
     * Add ORDER BY for entire UNION query.
     * Accepts ConditionBy object.
     *
     * @param ConditionBy $orderBy Object with fields for sorting
     *
     * @example
     * ->orderBy(ConditionBy::orderBy()->asc('name')->desc('created_at'))
     */
    public function orderBy(ConditionBy $orderBy): self
    {
        foreach ($orderBy->getItems() as $item) {
            $this->orderBy[] = [
                'field' => $item['field']->name,
                'direction' => $item['direction'] ?? QbConsts::ORDER_ASC,
            ];
        }

        return $this;
    }

    /**
     * Set LIMIT and OFFSET for entire UNION query.
     * If limit is 0 or negative, limit is not applied.
     *
     * @param int $limit  Number of records (must be > 0 to apply)
     * @param int $offset Offset (default 0, must be >= 0)
     */
    public function limit(int $limit, int $offset = 0): self
    {
        if ($limit <= 0) {
            $this->limitValue = null;
            $this->offsetValue = null;
        } else {
            $this->limitValue = $limit;
            $this->offsetValue = $offset > 0 ? $offset : null;
        }

        return $this;
    }

    /**
     * Build UNION SQL query.
     *
     * @param bool $compact Apply SQL compaction (default: false)
     *
     * @return string Ready SQL query
     */
    public function build(bool $compact = false): string
    {
        if ([] === $this->queries) {
            return '';
        }

        if (1 === \count($this->queries)) {
            return $this->queries[0];
        }

        $unionOperator = $this->useUnionAll ? "\nUNION ALL\n" : "\nUNION\n";
        $sql = implode($unionOperator, $this->queries);

        if ([] !== $this->orderBy || null !== $this->limitValue || null !== $this->offsetValue) {
            $sql = '('.$sql.')';
        }

        if ([] !== $this->orderBy) {
            $driver = $this->getDriverInstance();
            $orders = [];

            foreach ($this->orderBy as $order) {
                $orders[] = $driver->quoteName($order['field']).' '.$order['direction'];
            }
            $sql .= "\nORDER BY ".implode(', ', $orders);
        }

        if (null !== $this->limitValue) {
            $sql .= "\nLIMIT ".$this->limitValue;
        }

        if (null !== $this->offsetValue) {
            $sql .= "\nOFFSET ".$this->offsetValue;
        }

        if ($compact) {
            $sql = $this->compactor->compact($sql);
        }

        return $sql;
    }

    /**
     * Clear all accumulated queries.
     */
    public function reset(): self
    {
        $this->queries = [];
        $this->useUnionAll = false;
        $this->orderBy = [];
        $this->limitValue = null;
        $this->offsetValue = null;

        return $this;
    }

    /**
     * Get number of added queries.
     */
    public function count(): int
    {
        return \count($this->queries);
    }

    /**
     * Check if UNION has queries.
     */
    public function isEmpty(): bool
    {
        return [] === $this->queries;
    }

    /**
     * Get driver instance.
     */
    protected function getDriverInstance(): DriverInterface
    {
        return $this->queryBuilder->getDriverInstance();
    }
}
