<?php

declare(strict_types=1);

namespace QBuilder\Traits;

use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Services\SqlSecurity;

/**
 * Trait for SQL expressions in conflict builders.
 *
 * Provides common methods for setting expressions and CASE statements
 * in INSERT conflict handlers (ON CONFLICT, ON DUPLICATE KEY UPDATE, MERGE).
 *
 * @internal
 */
trait ExpressionsTrait
{
    /**
     * Set custom SQL expression with validation.
     *
     * @param string $field      Field name
     * @param string $expression SQL expression
     *
     * @throws InvalidIdentifierException
     */
    public function expression(string $field, string $expression): self
    {
        $validatedField = SqlSecurity::validateFieldName($field);

        if (1 !== preg_match('/^[a-zA-Z0-9_`"\[\].\s+\-*\/%()]+$/', $expression)) {
            throw new InvalidIdentifierException(
                "Invalid expression: '{$expression}'. Only alphanumeric, operators, and parentheses are allowed"
            );
        }

        $dangerousPatterns = '/;|--|\/\*|\*\/|xp_|sp_|exec\s|execute\s|'
            . 'drop\s+table|delete\s+from|insert\s+into|update\s+\w+\s+set/i';

        if (0 !== preg_match($dangerousPatterns, $expression)) {
            throw new InvalidIdentifierException(
                "Expression contains potentially dangerous SQL patterns: {$expression}"
            );
        }

        $this->validateFragment($expression, self::FRAGMENT_FORBIDDEN_KEYWORDS, 'expression');

        $this->updates[] = $this->quoteField($validatedField) . " = {$expression}";

        return $this;
    }

    /**
     * Set CASE expression with validation.
     *
     * @param string        $field      Field name
     * @param array<string> $conditions Array of WHEN and ELSE conditions
     *
     * @throws InvalidIdentifierException
     */
    public function caseExpression(string $field, array $conditions): self
    {
        $validatedField = SqlSecurity::validateFieldName($field);

        foreach ($conditions as $condition) {
            if (1 !== preg_match('/^(WHEN|ELSE)\s+.+$/i', $condition)) {
                throw new InvalidIdentifierException("Invalid CASE condition: {$condition}");
            }

            $dangerousPatterns = '/;|--|\/\*|\*\/|xp_|sp_|exec\s|execute\s|'
                . 'drop\s+table|delete\s+from|insert\s+into|update\s+\w+\s+set/i';

            if (0 !== preg_match($dangerousPatterns, $condition)) {
                throw new InvalidIdentifierException(
                    "CASE condition contains potentially dangerous SQL patterns: {$condition}"
                );
            }

            $this->validateFragment(
                $condition,
                array_values(array_diff(self::FRAGMENT_FORBIDDEN_KEYWORDS, ['WHEN', 'THEN', 'ELSE'])),
                'CASE condition'
            );
        }

        $caseExpr = 'CASE ' . implode(' ', $conditions) . ' END';
        $this->updates[] = $this->quoteField($validatedField) . " = {$caseExpr}";

        return $this;
    }

    /**
     * Quote field name using driver.
     * Must be implemented by the class using this trait.
     *
     * @param string $field Field name
     *
     * @return string Quoted field name
     */
    abstract protected function quoteField(string $field): string;

    /**
     * Validate an SQL fragment against the driver identifier quotes.
     * Must be implemented by the class using this trait.
     *
     * @param list<string> $forbiddenKeywords
     *
     * @throws InvalidIdentifierException
     */
    abstract protected function validateFragment(string $fragment, array $forbiddenKeywords, string $type): void;
}
