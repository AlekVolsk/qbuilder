<?php

namespace QBuilder\Drivers;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QueryBuilder;

/**
 * Database driver interface for QueryBuilder.
 *
 * Defines contract for specific SQL query building logic.
 */
interface DriverInterface
{
    /**
     * Get SQL builder for this driver.
     *
     * @param QueryBuilder $queryBuilder QueryBuilder instance
     */
    public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface;

    /**
     * Get quote character for identifiers (tables, fields).
     *
     * @return string Quote character
     */
    public function getIdentifierQuote(): string;

    /**
     * Get closing quote character for identifiers.
     *
     * @return string Closing quote character
     */
    public function getIdentifierCloseQuote(): string;

    /**
     * Get quote character for string values.
     *
     * @return string Quote character
     */
    public function getValueQuote(): string;

    /**
     * Quote identifier (table, field, alias).
     *
     * @param string $identifier Identifier to quote
     *
     * @return string Quoted identifier
     */
    public function quoteName(string $identifier): string;

    /**
     * Escape special characters in string value.
     *
     * @param string $value Value to escape
     *
     * @return string Escaped value
     */
    public function escapeValue(string $value): string;

    /**
     * Quote value for use in SQL.
     *
     * @param string $value Value to quote
     *
     * @return string Quoted value
     */
    public function quoteValue(string $value): string;

    /**
     * Escape special characters in LIKE pattern.
     *
     * @param string $pattern LIKE pattern
     *
     * @return string Escaped pattern
     */
    public function escapeLikePattern(string $pattern): string;

    /**
     * Get LIMIT string for pagination.
     *
     * @param int  $limit    Number of records
     * @param ?int $offset   Offset
     * @param bool $withTies Whether to use WITH TIES
     *
     * @return string SQL fragment for LIMIT
     */
    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string;

    /**
     * Does driver support WITH RECURSIVE (CTE).
     */
    public function supportsRecursiveCte(): bool;

    /**
     * Does driver support UNION queries.
     */
    public function supportsUnion(): bool;

    /**
     * Does driver support conflict handler on insert data.
     * For MySQL: ON DUPLICATE KEY UPDATE
     * For PostgreSQL: ON CONFLICT ... DO UPDATE.
     */
    public function supportsConflictHandler(): bool;

    /**
     * Create INSERT conflict handler builder.
     * For MySQL: ON DUPLICATE KEY UPDATE
     * For PostgreSQL: ON CONFLICT ... DO UPDATE.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     *
     * @throws UnsupportedFeatureException If driver does not support conflict handling
     */
    public function createConflictBuilder(QueryBuilder $queryBuilder): ConflictBuilderInterface;
}
