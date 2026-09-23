<?php

declare(strict_types=1);

namespace QBuilder\Condition;

use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * Abstract base class for condition builders.
 * Contains common methods for building SQL conditions (WHERE, HAVING, JOIN).
 */
abstract class Condition
{
    /** @var array<int,string> */
    protected array $conditions = [];

    /** @var array<string,?scalar> */
    protected array $bindParams = [];

    protected string $currentLogic = 'AND';
    protected int $paramCounter = 0;

    /**
     * @param QueryBuilder $queryBuilder Parent query builder
     * @param string       $clauseType   Condition type: 'WHERE', 'HAVING', 'JOIN' etc
     */
    public function __construct(protected QueryBuilder $queryBuilder, protected string $clauseType) {}

    /**
     * Equality: field = value.
     *
     * @param Field|string $field Field name
     * @param scalar       $value Comparison value
     */
    public function eq(Field|string $field, bool|float|int|string $value): static
    {
        $this->addCondition($field, '=', $value);

        return $this;
    }

    /**
     * Inequality: field != value.
     *
     * @param Field|string $field Field name
     * @param scalar       $value Comparison value
     */
    public function neq(Field|string $field, bool|float|int|string $value): static
    {
        $this->addCondition($field, '!=', $value);

        return $this;
    }

    /**
     * Greater than: field > value.
     *
     * @param Field|string $field Field name
     * @param scalar       $value Comparison value
     */
    public function gt(Field|string $field, bool|float|int|string $value): static
    {
        $this->addCondition($field, '>', $value);

        return $this;
    }

    /**
     * Greater than or equal: field >= value.
     *
     * @param Field|string $field Field name
     * @param scalar       $value Comparison value
     */
    public function gte(Field|string $field, bool|float|int|string $value): static
    {
        $this->addCondition($field, '>=', $value);

        return $this;
    }

    /**
     * Less than: field < value.
     *
     * @param Field|string $field Field name
     * @param scalar       $value Comparison value
     */
    public function lt(Field|string $field, bool|float|int|string $value): static
    {
        $this->addCondition($field, '<', $value);

        return $this;
    }

    /**
     * Less than or equal: field <= value.
     *
     * @param Field|string $field Field name
     * @param scalar       $value Comparison value
     */
    public function lte(Field|string $field, bool|float|int|string $value): static
    {
        $this->addCondition($field, '<=', $value);

        return $this;
    }

    /**
     * IN condition: field IN (value1, value2, ...).
     * Can be overridden in child classes for more complex logic.
     *
     * @param Field|string         $field  Field name
     * @param array<scalar>|string $values Array of values or comma-separated string
     */
    public function in(Field|string $field, array|string $values): static
    {
        $values = \is_array($values) ? $values : explode(',', $values);
        $values = array_filter($values, static fn ($v) => '' !== $v);

        if ([] === $values) {
            $this->addRawCondition('1 = 0');

            return $this;
        }

        $fieldName = $this->formatFieldName($field);
        $valuesList = implode(', ', array_map(fn ($v) => $this->formatValue($v), $values));

        $this->addRawCondition($fieldName.' IN ('.$valuesList.')');

        return $this;
    }

    /**
     * NOT IN condition: field NOT IN (value1, value2, ...).
     * Can be overridden in child classes for more complex logic.
     *
     * @param Field|string         $field  Field name
     * @param array<scalar>|string $values Array of values or comma-separated string
     */
    public function notIn(Field|string $field, array|string $values): static
    {
        $values = \is_array($values) ? $values : explode(',', $values);
        $values = array_filter($values, static fn ($v) => '' !== $v);

        if ([] === $values) {
            $this->addRawCondition('1 = 1');

            return $this;
        }

        $fieldName = $this->formatFieldName($field);
        $valuesList = implode(', ', array_map(fn ($v) => $this->formatValue($v), $values));

        $this->addRawCondition($fieldName.' NOT IN ('.$valuesList.')');

        return $this;
    }

    /**
     * LIKE condition: field LIKE pattern.
     *
     * @param Field|string $field    Field name
     * @param string       $value    Search pattern
     * @param string       $boundary Boundary type (LIKE_FULL, LIKE_LEFT, LIKE_RIGHT)
     */
    public function like(Field|string $field, string $value, string $boundary = QbConsts::LIKE_FULL): static
    {
        $driver = $this->getDriver();
        $escapedValue = $driver->escapeLikePattern($value);
        $finalValue = $this->applyLikeBoundary($escapedValue, $boundary);
        $this->addCondition($field, 'LIKE', $finalValue);

        return $this;
    }

    /**
     * NOT LIKE condition: field NOT LIKE pattern.
     *
     * @param Field|string $field    Field name
     * @param string       $value    Search pattern
     * @param string       $boundary Boundary type (LIKE_FULL, LIKE_LEFT, LIKE_RIGHT)
     */
    public function notLike(Field|string $field, string $value, string $boundary = QbConsts::LIKE_FULL): static
    {
        $driver = $this->getDriver();
        $escapedValue = $driver->escapeLikePattern($value);
        $finalValue = $this->applyLikeBoundary($escapedValue, $boundary);
        $this->addCondition($field, 'NOT LIKE', $finalValue);

        return $this;
    }

    /**
     * IS NULL condition: field IS NULL.
     *
     * @param Field|string $field Field name
     */
    public function isNull(Field|string $field): static
    {
        $fieldName = $this->formatFieldName($field);
        $this->addRawCondition($fieldName.' IS NULL');

        return $this;
    }

    /**
     * IS NOT NULL condition: field IS NOT NULL.
     *
     * @param Field|string $field Field name
     */
    public function isNotNull(Field|string $field): static
    {
        $fieldName = $this->formatFieldName($field);
        $this->addRawCondition($fieldName.' IS NOT NULL');

        return $this;
    }

    /**
     * BETWEEN condition: field BETWEEN min AND max.
     *
     * @param Field|string $field Field name
     * @param scalar       $min   Minimum value
     * @param scalar       $max   Maximum value
     */
    public function between(
        Field|string $field,
        bool|float|int|string $min,
        bool|float|int|string $max
    ): static {
        $fieldName = $this->formatFieldName($field);
        $minValue = $this->formatValue($min);
        $maxValue = $this->formatValue($max);

        $this->addRawCondition($fieldName.' BETWEEN '.$minValue.' AND '.$maxValue);

        return $this;
    }

    /**
     * NOT BETWEEN condition: field NOT BETWEEN min AND max.
     *
     * @param Field|string $field Field name
     * @param scalar       $min   Minimum value
     * @param scalar       $max   Maximum value
     */
    public function notBetween(
        Field|string $field,
        bool|float|int|string $min,
        bool|float|int|string $max
    ): static {
        $fieldName = $this->formatFieldName($field);
        $minValue = $this->formatValue($min);
        $maxValue = $this->formatValue($max);

        $this->addRawCondition($fieldName.' NOT BETWEEN '.$minValue.' AND '.$maxValue);

        return $this;
    }

    /**
     * Add raw SQL condition.
     *
     * @param string $condition Raw SQL condition
     */
    public function raw(string $condition): static
    {
        $this->addRawCondition($condition);

        return $this;
    }

    /**
     * Add bitmask condition (bitwise AND operations).
     * Checks if specific bits are set in the field value.
     * Supported: MySQL, PostgreSQL, ClickHouse, MS SQL, Oracle, SQLite.
     *
     * @param Field|string         $field Field name or Field object
     * @param array<int, bool|int> $masks Array of bit masks [bit => state]
     *                                    state: 1/true = bit must be set, 0/false = bit must be unset
     *
     * @example
     * // Check if bits 1 and 4 are set, and bit 2 is not set
     * ->bitmask('flags', [1 => 1, 2 => 0, 4 => 1])
     * // MySQL: WHERE (flags & 1 = 1) AND (flags & 2 = 0) AND (flags & 4 = 4)
     *
     * // Check if user has specific permissions
     * ->bitmask('permissions', [
     *     1 => true,  // READ permission must be set
     *     2 => true,  // WRITE permission must be set
     *     4 => false, // DELETE permission must NOT be set
     * ])
     */
    public function bitmask(Field|string $field, array $masks): static
    {
        $fieldName = $this->formatFieldName($field);
        $conditions = [];

        foreach ($masks as $bit => $state) {
            $bitValue = (int) $bit;
            $stateValue = (int) $state;

            if (0 === $stateValue) {
                $conditions[] = "({$fieldName} & {$bitValue} = 0)";
            } else {
                $conditions[] = "({$fieldName} & {$bitValue} = {$bitValue})";
            }
        }

        if ([] !== $conditions) {
            $this->addRawCondition(implode(' AND ', $conditions));
        }

        return $this;
    }

    /**
     * Add NOT bitmask condition (negated bitwise AND operations).
     * Checks if the bitmask condition is NOT met.
     * Supported: MySQL, PostgreSQL, ClickHouse, MS SQL, Oracle, SQLite.
     *
     * @param Field|string         $field Field name or Field object
     * @param array<int, bool|int> $masks Array of bit masks [bit => state]
     *
     * @example
     * // Exclude records where bits 1 and 4 are set
     * ->notBitmask('flags', [1 => 1, 4 => 1])
     * // MySQL: WHERE NOT ((flags & 1 = 1) AND (flags & 4 = 4))
     */
    public function notBitmask(Field|string $field, array $masks): static
    {
        $fieldName = $this->formatFieldName($field);
        $conditions = [];

        foreach ($masks as $bit => $state) {
            $bitValue = (int) $bit;
            $stateValue = (int) $state;

            if (0 === $stateValue) {
                $conditions[] = "({$fieldName} & {$bitValue} = 0)";
            } else {
                $conditions[] = "({$fieldName} & {$bitValue} = {$bitValue})";
            }
        }

        if ([] !== $conditions) {
            $this->addRawCondition('NOT ('.implode(' AND ', $conditions).')');
        }

        return $this;
    }

    /**
     * Add FIND_IN_SET condition (check if value exists in comma-separated list).
     * Supported: MySQL, MariaDB.
     * Not supported: PostgreSQL, ClickHouse, MS SQL, Oracle, SQLite (throws exception).
     *
     * @param Field|string         $field  Field name containing comma-separated values
     * @param array<string>|string $values Value(s) to find in the set
     *
     * @throws UnsupportedFeatureException If driver does not support FIND_IN_SET
     *
     * @example
     * // Find users with specific tag
     * ->findInSet('tags', 'admin')
     * // MySQL: WHERE FIND_IN_SET('admin', tags)
     *
     * // Find users with any of specified tags
     * ->findInSet('tags', ['admin', 'moderator'])
     * // MySQL: WHERE (FIND_IN_SET('admin', tags) OR FIND_IN_SET('moderator', tags))
     */
    public function findInSet(Field|string $field, array|string $values): static
    {
        $driverName = $this->queryBuilder->getDriver();

        if (! \in_array($driverName, [QbConsts::DRIVER_MYSQL, QbConsts::DRIVER_PDO_MYSQL], true)) {
            throw new UnsupportedFeatureException(
                "FIND_IN_SET is not supported by driver '{$driverName}'. "
                    .'This function is only available in MySQL/MariaDB. '
                    .'Consider using alternative approaches like JSON fields or normalized tables.'
            );
        }

        $fieldName = $this->formatFieldName($field);
        $values = \is_array($values) ? $values : [$values];
        $conditions = [];

        foreach ($values as $value) {
            $escapedValue = $this->formatValue((string) $value);
            $conditions[] = "FIND_IN_SET({$escapedValue}, {$fieldName})";
        }

        if ([] !== $conditions) {
            if (1 === \count($conditions)) {
                $this->addRawCondition($conditions[0]);
            } else {
                $this->addRawCondition(implode(' OR ', $conditions));
            }
        }

        return $this;
    }

    /**
     * Build SQL conditions string.
     *
     * @return string SQL conditions
     */
    public function build(): string
    {
        if ([] === $this->conditions) {
            return '';
        }
        $sqlParts = $this->conditions;

        return ' '.implode(' ', $sqlParts);
    }

    /**
     * Check if conditions exist.
     */
    public function hasConditions(): bool
    {
        return [] !== $this->conditions;
    }

    /**
     * Clear all conditions.
     */
    public function reset(): void
    {
        $this->conditions = [];
        $this->bindParams = [];
        $this->currentLogic = 'AND';
        $this->paramCounter = 0;
    }

    /**
     * Equality between two fields: field1 = field2.
     *
     * @param string       $table1 First field table/alias (optional)
     * @param Field|string $field1 First field name
     * @param string       $table2 Second field table/alias (optional)
     * @param Field|string $field2 Second field name
     */
    public function eqField(
        string $table1,
        Field|string $field1,
        string $table2,
        Field|string $field2
    ): static {
        return $this->compareFields($table1, $field1, '=', $table2, $field2);
    }

    /**
     * Inequality between two fields: field1 != field2.
     *
     * @param string       $table1 First field table/alias (optional)
     * @param Field|string $field1 First field name
     * @param string       $table2 Second field table/alias (optional)
     * @param Field|string $field2 Second field name
     */
    public function neqField(
        string $table1,
        Field|string $field1,
        string $table2,
        Field|string $field2
    ): static {
        return $this->compareFields($table1, $field1, '!=', $table2, $field2);
    }

    /**
     * Greater than between two fields: field1 > field2.
     *
     * @param string       $table1 First field table/alias (optional)
     * @param Field|string $field1 First field name
     * @param string       $table2 Second field table/alias (optional)
     * @param Field|string $field2 Second field name
     */
    public function gtField(
        string $table1,
        Field|string $field1,
        string $table2,
        Field|string $field2
    ): static {
        return $this->compareFields($table1, $field1, '>', $table2, $field2);
    }

    /**
     * Greater than or equal between two fields: field1 >= field2.
     *
     * @param string       $table1 First field table/alias (optional)
     * @param Field|string $field1 First field name
     * @param string       $table2 Second field table/alias (optional)
     * @param Field|string $field2 Second field name
     */
    public function gteField(
        string $table1,
        Field|string $field1,
        string $table2,
        Field|string $field2
    ): static {
        return $this->compareFields($table1, $field1, '>=', $table2, $field2);
    }

    /**
     * Less than between two fields: field1 < field2.
     *
     * @param string       $table1 First field table/alias (optional)
     * @param Field|string $field1 First field name
     * @param string       $table2 Second field table/alias (optional)
     * @param Field|string $field2 Second field name
     */
    public function ltField(
        string $table1,
        Field|string $field1,
        string $table2,
        Field|string $field2
    ): static {
        return $this->compareFields($table1, $field1, '<', $table2, $field2);
    }

    /**
     * Less than or equal between two fields: field1 <= field2.
     *
     * @param string       $table1 First field table/alias (optional)
     * @param Field|string $field1 First field name
     * @param string       $table2 Second field table/alias (optional)
     * @param Field|string $field2 Second field name
     */
    public function lteField(
        string $table1,
        Field|string $field1,
        string $table2,
        Field|string $field2
    ): static {
        return $this->compareFields($table1, $field1, '<=', $table2, $field2);
    }

    /**
     * Get driver instance.
     */
    protected function getDriver(): DriverInterface
    {
        return $this->queryBuilder->getDriverInstance();
    }

    /**
     * Add condition with operator.
     *
     * @param Field|string $field    Field name
     * @param string       $operator Comparison operator
     * @param scalar       $value    Value
     */
    protected function addCondition(Field|string $field, string $operator, bool|float|int|string $value): void
    {
        $fieldName = $this->formatFieldName($field);
        $valueStr = $this->formatValue($value);

        $condition = $fieldName.' '.$operator.' '.$valueStr;
        $this->addRawCondition($condition);
    }

    /**
     * Add raw condition.
     *
     * @param string $condition SQL condition
     */
    protected function addRawCondition(string $condition): void
    {
        if ([] !== $this->conditions) {
            $this->conditions[] = $this->currentLogic;
        }

        $this->conditions[] = '('.$condition.')';

        $this->currentLogic = 'AND';
    }

    /**
     * Format field name for SQL.
     *
     * @param Field|string $field Field name
     *
     * @return string Formatted field name
     */
    protected function formatFieldName(Field|string $field): string
    {
        $driver = $this->getDriver();

        if ($field instanceof Field) {
            $field->redetectWithDriver($this->queryBuilder->getDriver());

            $fieldName = $field->name;
            $tableOrAlias = $field->tableOrAlias;

            if ($field->isExpression()) {
                return $fieldName;
            }

            if ('' !== $tableOrAlias) {
                SqlSecurity::validateTableName($tableOrAlias, $this->queryBuilder->getDriver());
                SqlSecurity::validateFieldName($fieldName, $this->queryBuilder->getDriver());

                return $driver->quoteName($tableOrAlias).'.'.$driver->quoteName($fieldName);
            }

            SqlSecurity::validateFieldName($fieldName, $this->queryBuilder->getDriver());

            return $driver->quoteName($fieldName);
        }

        if (str_contains($field, '.')) {
            [$table, $fieldName] = explode('.', $field, 2);
            SqlSecurity::validateTableName($table, $this->queryBuilder->getDriver());
            SqlSecurity::validateFieldName($fieldName, $this->queryBuilder->getDriver());

            return $driver->quoteName($table).'.'.$driver->quoteName($fieldName);
        }

        SqlSecurity::validateFieldName($field, $this->queryBuilder->getDriver());

        return $driver->quoteName($field);
    }

    /**
     * Format value for SQL.
     *
     * @param scalar $value Value to format
     *
     * @return string Formatted value
     */
    protected function formatValue(bool|float|int|string $value): string
    {
        $driver = $this->getDriver();

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return $driver->quoteValue($value);
    }

    /**
     * Apply LIKE boundary to value.
     *
     * @param string $value    Source value
     * @param string $boundary Boundary type (LIKE_FULL, LIKE_LEFT, LIKE_RIGHT)
     *
     * @return string Value with added % signs
     */
    protected function applyLikeBoundary(string $value, string $boundary): string
    {
        if (str_starts_with($value, '%') || str_ends_with($value, '%')) {
            return $value;
        }

        return match ($boundary) {
            QbConsts::LIKE_LEFT => '%'.$value,
            QbConsts::LIKE_RIGHT => $value.'%',
            default => '%'.$value.'%',
        };
    }

    /**
     * Compare two fields with operator.
     *
     * @param string       $table1   First field table/alias (optional)
     * @param Field|string $field1   First field name
     * @param string       $operator Comparison operator
     * @param string       $table2   Second field table/alias (optional)
     * @param Field|string $field2   Second field name
     */
    protected function compareFields(
        string $table1,
        Field|string $field1,
        string $operator,
        string $table2,
        Field|string $field2
    ): static {
        if ($field1 instanceof Field) {
            $field1Name = $this->formatFieldName($field1);
        } elseif ('' !== $table1) {
            $field1Name = $this->formatFieldName(new Field($field1, $table1));
        } else {
            $field1Name = $this->formatFieldName($field1);
        }

        if ($field2 instanceof Field) {
            $field2Name = $this->formatFieldName($field2);
        } elseif ('' !== $table2) {
            $field2Name = $this->formatFieldName(new Field($field2, $table2));
        } else {
            $field2Name = $this->formatFieldName($field2);
        }

        $this->addRawCondition($field1Name.' '.$operator.' '.$field2Name);

        return $this;
    }
}
