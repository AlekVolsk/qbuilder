<?php

namespace QBuilder\Builder;

/**
 * Interface for INSERT conflict handling builders.
 *
 * Defines common contract for MySQL (ON DUPLICATE KEY UPDATE)
 * and PostgreSQL (ON CONFLICT ... DO UPDATE).
 */
interface ConflictBuilderInterface
{
    /**
     * Set field value.
     *
     * @param string  $field Field name
     * @param ?scalar $value Value
     */
    public function set(string $field, bool|float|int|string|null $value): self;

    /**
     * Increment field.
     *
     * @param string           $field Field name
     * @param float|int|string $value Value to add
     */
    public function increment(string $field, float|int|string $value = 1): self;

    /**
     * Decrement field.
     *
     * @param string           $field Field name
     * @param float|int|string $value Value to subtract
     */
    public function decrement(string $field, float|int|string $value = 1): self;

    /**
     * Set field to NULL.
     *
     * @param string $field Field name
     */
    public function setNull(string $field): self;

    /**
     * Set SQL function.
     *
     * @param string $field        Field name
     * @param string $functionCall SQL function
     */
    public function sqlFunction(string $field, string $functionCall): self;

    /**
     * Set SQL expression.
     *
     * @param string $field      Field name
     * @param string $expression SQL expression
     */
    public function expression(string $field, string $expression): self;

    /**
     * Set CASE expression.
     *
     * @param string        $field      Field name
     * @param array<string> $conditions WHEN/ELSE conditions
     */
    public function caseExpression(string $field, array $conditions): self;

    /**
     * Specify conflict fields (ON CONFLICT (field1, field2)).
     *
     * @param array<int,string> $fields Fields by which conflict is determined
     */
    public function conflictTarget(array $fields): self;

    /**
     * Set field equal to value from inserted data (EXCLUDED.field).
     *
     * @param string $field         Field name for update
     * @param string $excludedField Field name from inserted data (by default = $field)
     */
    public function excluded(string $field, string $excludedField = ''): self;

    /**
     * Build final SQL.
     */
    public function build(): string;
}
