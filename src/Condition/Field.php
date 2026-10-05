<?php

declare(strict_types=1);

namespace QBuilder\Condition;

use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * Universal class for representing a database field.
 * Can contain field name, table/alias and optionally field alias.
 * Supports SQL expressions with aggregate functions and calculations.
 *
 * @api
 */
class Field
{
    protected bool $isExpression = false;
    protected ?QueryBuilder $subqueryInstance = null;

    public function __construct(
        public readonly string $name,
        public readonly string $tableOrAlias = '',
        protected string $fieldAlias = ''
    ) {
        $this->validateAndDetectExpression();
    }

    /**
     * Static constructor for convenient Field creation.
     *
     * @param string $name         Field name or SQL expression
     * @param string $tableOrAlias Table or alias (optional)
     * @param string $fieldAlias   Field alias (optional)
     *
     * @example
     * // Simple field
     * Field::set('id')
     *
     * // Field with table
     * Field::set('id', 'users')
     *
     * // SQL expression with alias
     * Field::set('COUNT(*)', '', 'total')
     * Field::set('SUM(price)', 'orders', 'total_amount')
     */
    public static function set(string $name, string $tableOrAlias = '', string $fieldAlias = ''): self
    {
        return new self($name, $tableOrAlias, $fieldAlias);
    }

    /**
     * Create field from subquery (for SELECT).
     * Safe alternative to Field::set('(' . $subquery->build() . ')', '', 'alias').
     *
     * @param QueryBuilder $subquery QueryBuilder instance
     * @param string       $alias    Field alias
     *
     * @throws InvalidQueryException If subquery is not SELECT
     *
     * @example
     * // Subquery in SELECT
     * $subquery = $qb->select('COUNT(*)')->from('orders')
     *     ->where()->raw('user_id = users.id')->end();
     *
     * $sql = $qb->select('id', 'name', Field::subquery($subquery, 'order_count'))
     *     ->from('users')
     *     ->build();
     * // SELECT `id`, `name`, (SELECT COUNT(*) FROM `orders` WHERE user_id = users.id) AS `order_count`
     * // FROM `users`
     */
    public static function subquery(QueryBuilder $subquery, string $alias = ''): self
    {
        if ('SELECT' !== $subquery->getType()) {
            throw new InvalidQueryException('Subquery must be a SELECT statement');
        }

        $field = new self('__SUBQUERY_PLACEHOLDER__', '', $alias);
        $field->subqueryInstance = $subquery;
        $field->isExpression = true;

        return $field;
    }

    /**
     * Check if field is a subquery.
     */
    public function isSubquery(): bool
    {
        return $this->subqueryInstance instanceof QueryBuilder;
    }

    /**
     * Get subquery instance.
     */
    public function getSubquery(): ?QueryBuilder
    {
        return $this->subqueryInstance;
    }

    public function getFieldAlias(): string
    {
        return $this->fieldAlias;
    }

    public function isExpression(): bool
    {
        return $this->isExpression;
    }

    public function setFieldAlias(string $alias): self
    {
        $this->fieldAlias = $alias;

        return $this;
    }

    /**
     * Re-detect expression status with specific driver.
     * Useful when Field was created without driver context.
     * Note: This method only updates isExpression flag without validation.
     *
     * @param string $driver Driver name (e.g., 'mysql', 'pgsql', 'oracle')
     *
     * @example
     * $field = Field::set('string_agg(name)');
     * $field->redetectWithDriver('pgsql'); // Will correctly detect PostgreSQL function
     */
    public function redetectWithDriver(string $driver): self
    {
        $this->isExpression = $this->detectExpression($this->name, $driver);

        return $this;
    }

    /**
     * Check if string is an unsigned integer literal, e.g. 1 in SELECT 1.
     *
     * @param string $field Field name or expression
     */
    public static function isIntegerLiteral(string $field): bool
    {
        return 1 === preg_match('/^\d+$/', $field);
    }

    /**
     * Validate field and detect if it is a SQL expression.
     * Uses permissive detection by checking all drivers to avoid false negatives.
     */
    protected function validateAndDetectExpression(): void
    {
        $trimmed = trim($this->name);

        if ('' === $trimmed) {
            throw new InvalidIdentifierException('Field name cannot be empty');
        }

        $this->isExpression = $this->detectExpressionPermissive($trimmed);

        if (! $this->isExpression && '*' !== $trimmed) {
            SqlSecurity::validateFieldName($trimmed);
        }

        if ('' !== $this->tableOrAlias && '0' !== $this->tableOrAlias) {
            SqlSecurity::validateIdentifier($this->tableOrAlias, 'table/alias');
        }

        if ('' !== $this->fieldAlias && '0' !== $this->fieldAlias) {
            SqlSecurity::validateAliasName($this->fieldAlias);
        }
    }

    /**
     * Detect if string is a SQL expression using all available drivers.
     * Used in constructor to avoid false negatives when driver is unknown.
     *
     * @param string $field Field name or expression
     */
    protected function detectExpressionPermissive(string $field): bool
    {
        if ('*' === $field) {
            return false;
        }

        if (self::isIntegerLiteral($field)) {
            return true;
        }

        $drivers = QbConsts::getSupportedDrivers();

        foreach ($drivers as $driver) {
            $allFunctions = SqlSecurity::getSqlFunctions($driver);

            foreach ($allFunctions as $func) {
                if (1 === preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $field)) {
                    return true;
                }
            }
        }

        if (1 === preg_match('/[\+\-\*\/\%]/', $field)) {
            return true;
        }

        if (1 === preg_match('/\bCASE\b/i', $field)) {
            return true;
        }

        return (bool) preg_match('/\(\s*SELECT\b/i', $field);
    }

    /**
     * Detect if string is a SQL expression.
     * Uses driver-specific function list if provided for better accuracy.
     *
     * @param string $field  Field name or expression
     * @param string $driver Driver name for driver-specific function detection
     */
    protected function detectExpression(string $field, string $driver): bool
    {
        if ('*' === $field) {
            return false;
        }

        if (self::isIntegerLiteral($field)) {
            return true;
        }

        $allFunctions = SqlSecurity::getSqlFunctions($driver);

        foreach ($allFunctions as $func) {
            if (1 === preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $field)) {
                return true;
            }
        }

        if (1 === preg_match('/[\+\-\*\/\%]/', $field)) {
            return true;
        }

        if (1 === preg_match('/\bCASE\b/i', $field)) {
            return true;
        }

        return (bool) preg_match('/\(\s*SELECT\b/i', $field);
    }
}
