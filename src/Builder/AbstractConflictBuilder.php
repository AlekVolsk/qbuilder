<?php

declare(strict_types=1);

namespace QBuilder\Builder;

use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * Abstract base class for conflict builders.
 *
 * Provides common functionality for:
 * - MySQL: ON DUPLICATE KEY UPDATE
 * - PostgreSQL: ON CONFLICT ... DO UPDATE
 * - SQLite: ON CONFLICT ... DO UPDATE
 * - MSSQL: MERGE
 * - Oracle: MERGE
 *
 * @internal
 */
abstract class AbstractConflictBuilder implements ConflictBuilderInterface
{
    /**
     * Keywords that let an SQL fragment read other data or leave its assignment.
     */
    protected const array FRAGMENT_FORBIDDEN_KEYWORDS = [
        'SELECT',
        'UNION',
        'INSERT',
        'UPDATE',
        'DELETE',
        'DROP',
        'MERGE',
        'EXEC',
        'EXECUTE',
        'CALL',
        'INTO',
        'WHERE',
        'RETURNING',
        'OUTPUT',
        'CASE',
        'WHEN',
        'THEN',
        'ELSE',
        'END',
    ];

    /**
     * Assignments; a closure is resolved on build, when the INSERT table is known.
     *
     * @var list<\Closure(): string|string>
     */
    protected array $updates = [];

    /** @var array<string> */
    protected array $conflictFields = [];

    /**
     * @param QueryBuilder    $queryBuilder Parent QueryBuilder
     * @param DriverInterface $driver       Database driver
     */
    public function __construct(protected QueryBuilder $queryBuilder, protected DriverInterface $driver) {}

    /**
     * Specify conflict target fields.
     *
     * @param array<int,string> $fields Fields for matching
     */
    public function conflictTarget(array $fields): self
    {
        foreach ($fields as $field) {
            $this->conflictFields[] = SqlSecurity::validateFieldName($field);
        }

        return $this;
    }

    /**
     * Set field value with proper escaping.
     *
     * SECURITY: This method always escapes string values to prevent SQL injection.
     * For SQL expressions (functions, column references), use expression() or sqlFunction() instead.
     *
     * @param string  $field Field name
     * @param ?scalar $value Value (will be escaped if string)
     *
     * @throws InvalidIdentifierException
     *
     * @example
     * // Safe: values are escaped
     * ->set('name', $userInput)
     * ->set('count', 42)
     * ->set('active', true)
     *
     * // For SQL expressions, use dedicated methods:
     * ->expression('updated_at', 'NOW()')
     * ->sqlFunction('count', 'count + 1')
     */
    public function set(string $field, bool|float|int|string|null $value): self
    {
        $field = SqlSecurity::validateFieldName($field);

        $this->updates[] = $this->quoteField($field) . ' = ' . $this->getDriver()->formatValue($value);

        return $this;
    }

    /**
     * Increment field.
     *
     * @param string                   $field Field name
     * @param float|int|numeric-string $value Value to add
     *
     * @throws InvalidIdentifierException
     * @throws InvalidQueryException      If the value is not a finite number
     */
    public function increment(string $field, float|int|string $value = 1): self
    {
        $field = SqlSecurity::validateFieldName($field);
        $quotedField = $this->quoteField($field);

        $operand = self::formatNumericOperand($value);
        $this->updates[] = fn (): string => $quotedField . ' = '
            . $this->currentValueReference($quotedField) . ' + ' . $operand;

        return $this;
    }

    /**
     * Decrement field.
     *
     * @param string                   $field Field name
     * @param float|int|numeric-string $value Value to subtract
     *
     * @throws InvalidIdentifierException
     * @throws InvalidQueryException      If the value is not a finite number
     */
    public function decrement(string $field, float|int|string $value = 1): self
    {
        $field = SqlSecurity::validateFieldName($field);
        $quotedField = $this->quoteField($field);

        $operand = self::formatNumericOperand($value);
        $this->updates[] = fn (): string => $quotedField . ' = '
            . $this->currentValueReference($quotedField) . ' - ' . $operand;

        return $this;
    }

    /**
     * Set field to NULL.
     *
     * @param string $field Field name
     *
     * @throws InvalidIdentifierException
     */
    public function setNull(string $field): self
    {
        $field = SqlSecurity::validateFieldName($field);

        $this->updates[] = $this->quoteField($field) . ' = NULL';

        return $this;
    }

    /**
     * Set SQL function.
     *
     * @param string $field        Field name
     * @param string $functionCall SQL function
     *
     * @throws InvalidIdentifierException
     */
    public function sqlFunction(string $field, string $functionCall): self
    {
        $field = SqlSecurity::validateFieldName($field);
        $this->validateFragment($functionCall, self::FRAGMENT_FORBIDDEN_KEYWORDS, 'SQL function');

        $this->updates[] = $this->quoteField($field) . ' = ' . $functionCall;

        return $this;
    }

    /**
     * Set SQL expression.
     *
     * @param string $field      Field name
     * @param string $expression SQL expression
     *
     * @throws InvalidIdentifierException
     */
    abstract public function expression(string $field, string $expression): self;

    /**
     * Set CASE expression.
     *
     * @param string        $field      Field name
     * @param array<string> $conditions WHEN/ELSE conditions
     *
     * @throws InvalidIdentifierException
     */
    abstract public function caseExpression(string $field, array $conditions): self;

    /**
     * Set field equal to value from inserted/excluded data.
     *
     * @param string $field         Field name for update
     * @param string $excludedField Field name from inserted data (by default = $field)
     */
    abstract public function excluded(string $field, string $excludedField = ''): self;

    /**
     * Build final SQL.
     */
    abstract public function build(): string;

    /**
     * Get driver instance.
     */
    protected function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * Validate an SQL fragment against the driver identifier quotes.
     *
     * @param list<string> $forbiddenKeywords
     *
     * @throws InvalidIdentifierException
     */
    protected function validateFragment(string $fragment, array $forbiddenKeywords, string $type): void
    {
        $driver = $this->getDriver();

        SqlSecurity::validateSqlFragment(
            $fragment,
            $driver->getIdentifierQuote(),
            $driver->getIdentifierCloseQuote(),
            $forbiddenKeywords,
            $type
        );
    }

    /**
     * Reference to the current value of a field in the conflicting row.
     *
     * @param string $quotedField Quoted field name
     */
    protected function currentValueReference(string $quotedField): string
    {
        return $quotedField;
    }

    /**
     * Assignments joined for the UPDATE part.
     */
    protected function buildUpdates(): string
    {
        return implode(', ', array_map(
            static fn (\Closure|string $update): string => $update instanceof \Closure ? $update() : $update,
            $this->updates
        ));
    }

    /**
     * Quote field via driver.
     *
     * @param string $field Field name
     */
    protected function quoteField(string $field): string
    {
        return $this->getDriver()->quoteName($field);
    }

    /**
     * Format a numeric operand of increment/decrement.
     *
     * @throws InvalidQueryException If the value is not a finite number
     */
    private static function formatNumericOperand(float|int|string $value): string
    {
        if (\is_string($value)) {
            if (! is_numeric($value)) {
                throw new InvalidQueryException("Increment/decrement value must be numeric, '{$value}' given");
            }

            return trim($value);
        }

        if (\is_float($value) && ! is_finite($value)) {
            throw new InvalidQueryException('Increment/decrement value must be a finite number');
        }

        return (string) $value;
    }
}
