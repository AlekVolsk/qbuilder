<?php

namespace QBuilder\Drivers;

use QBuilder\QueryBuilder;

/**
 * Abstract base driver for QueryBuilder.
 *
 * Provides common functionality for all database drivers while allowing
 * specific implementations to override behavior as needed.
 */
abstract class AbstractDriver implements DriverInterface
{
    public function __construct() {}

    /**
     * Get SQL builder instance for this driver.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     */
    abstract public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface;

    /**
     * Get the identifier quote character(s) for this driver.
     *
     * @return string Quote character(s): `, ", or [
     */
    abstract public function getIdentifierQuote(): string;

    /**
     * Get the closing identifier quote character for this driver.
     *
     * @return string Closing quote character: `, ", or ]
     */
    abstract public function getIdentifierCloseQuote(): string;

    /**
     * Get the value quote character for this driver.
     *
     * @return string Quote character: ' or "
     */
    abstract public function getValueQuote(): string;

    /**
     * Escape special characters in string value.
     *
     * @param string $value Value to escape
     *
     * @return string Escaped value
     */
    abstract public function escapeValue(string $value): string;

    /**
     * Get the driver name.
     *
     * @return string Driver name (mysql, pgsql, sqlite, mssql, oracle, clickhouse)
     */
    abstract public function getName(): string;

    /**
     * Quote an identifier (table name, column name, etc.).
     *
     * Handles:
     * - Asterisk (*) - returns as-is
     * - Dotted identifiers (schema.table.column)
     * - Escaping of quote characters
     *
     * @param string $identifier Identifier to quote
     *
     * @return string Quoted identifier
     */
    public function quoteName(string $identifier): string
    {
        if ('*' === $identifier) {
            return '*';
        }

        $quote = $this->getIdentifierQuote();
        $escape = $this->getEscapeChar();

        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);
            $parts = array_map(
                fn ($part): string => $quote.str_replace($quote, $escape, $this->transformIdentifier($part))
                    .$this->getClosingQuote(),
                $parts
            );

            return implode('.', $parts);
        }

        return $quote.str_replace($quote, $escape, $this->transformIdentifier($identifier))
            .$this->getClosingQuote();
    }

    /**
     * Quote a value for SQL query.
     *
     * Implements custom quoting without relying on PDO::quote().
     * Escapes single quotes and wraps value in single quotes.
     *
     * @param string $value Value to quote
     *
     * @return string Quoted value
     */
    public function quoteValue(string $value): string
    {
        $quote = $this->getValueQuote();
        $escaped = $this->escapeValue($value);

        return $quote.$escaped.$quote;
    }

    /**
     * Escape LIKE pattern special characters.
     *
     * Default implementation escapes:
     * - Backslash (\)
     * - Percent (%)
     * - Underscore (_)
     *
     * Override in specific drivers for different behavior.
     *
     * @param string $pattern LIKE pattern
     *
     * @return string Escaped pattern
     */
    public function escapeLikePattern(string $pattern): string
    {
        $pattern = str_replace('\\', '\\\\', $pattern);
        $pattern = str_replace('%', '\%', $pattern);

        return str_replace('_', '\_', $pattern);
    }

    /**
     * Check if driver supports recursive CTEs (WITH RECURSIVE).
     */
    public function supportsRecursiveCte(): bool
    {
        return true;
    }

    /**
     * Check if driver supports UNION queries.
     */
    public function supportsUnion(): bool
    {
        return true;
    }

    /**
     * Check if driver supports conflict handlers (ON CONFLICT, ON DUPLICATE KEY, MERGE).
     */
    public function supportsConflictHandler(): bool
    {
        return true;
    }

    /**
     * Transform identifier before quoting.
     *
     * Default: no transformation.
     * Oracle: converts to uppercase.
     *
     * @param string $identifier Identifier to transform
     *
     * @return string Transformed identifier
     */
    protected function transformIdentifier(string $identifier): string
    {
        return $identifier;
    }

    /**
     * Get escape sequence for quote character.
     *
     * Default: double the quote character.
     * MSSQL: ]] for closing bracket.
     *
     * @return string Escape sequence
     */
    protected function getEscapeChar(): string
    {
        $quote = $this->getIdentifierQuote();

        return match ($quote) {
            '[' => ']]',
            default => $quote.$quote,
        };
    }

    /**
     * Get closing quote character.
     *
     * Default: same as opening quote.
     * MSSQL: ] for opening [.
     *
     * @return string Closing quote character
     */
    protected function getClosingQuote(): string
    {
        return match ($this->getIdentifierQuote()) {
            '[' => ']',
            default => $this->getIdentifierQuote(),
        };
    }
}
