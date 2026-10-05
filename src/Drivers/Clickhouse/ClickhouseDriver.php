<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Clickhouse;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Drivers\AbstractDriver;
use QBuilder\Drivers\SqlBuilderInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QueryBuilder;

/**
 * ClickHouse driver for QueryBuilder.
 *
 * Implements specific SQL query building logic for ClickHouse.
 * ClickHouse is column-oriented DBMS optimized for OLAP queries.
 *
 * @internal
 */
final class ClickhouseDriver extends AbstractDriver
{
    public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface
    {
        return new ClickhouseSqlBuilder($queryBuilder, $this);
    }

    public function getIdentifierQuote(): string
    {
        return '`';
    }

    public function getIdentifierCloseQuote(): string
    {
        return '`';
    }

    public function getValueQuote(): string
    {
        return "'";
    }

    public function escapeValue(string $value): string
    {
        return str_replace(
            ['\\', "'", "\n", "\r", "\t", "\0"],
            ['\\\\', "\\'", '\n', '\r', '\t', '\0'],
            $value
        );
    }

    /**
     * Escape LIKE pattern with backslash, the only LIKE escape mechanism in ClickHouse.
     *
     * @param string $pattern LIKE pattern
     *
     * @return string Escaped pattern
     */
    #[\Override]
    public function escapeLikePattern(string $pattern): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $pattern);
    }

    #[\Override]
    public function getLikeEscapeClause(): string
    {
        return '';
    }

    #[\Override]
    public function unionTailAppliesToLastQuery(): bool
    {
        return true;
    }

    /**
     * A bare UNION is rejected unless the union_default_mode setting is set.
     */
    #[\Override]
    public function unionOperator(bool $all): string
    {
        return $all ? 'UNION ALL' : 'UNION DISTINCT';
    }

    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string
    {
        $sql = "\nLIMIT ";

        if (null !== $offset && $offset > 0) {
            $sql .= $offset . ', ';
        }

        $sql .= $limit;

        if ($withTies) {
            $sql .= ' WITH TIES';
        }

        return $sql;
    }

    public function supportsRecursiveCte(): bool
    {
        return false;
    }

    public function supportsConflictHandler(): bool
    {
        return false;
    }

    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter -- signature from DriverInterface
    public function createConflictBuilder(QueryBuilder $queryBuilder): ConflictBuilderInterface
    {
        throw new UnsupportedFeatureException(
            'ClickHouse does not support conflict handlers (ON DUPLICATE KEY UPDATE / ON CONFLICT). '
                . 'Use ReplacingMergeTree or CollapsingMergeTree engines for data deduplication.'
        );
    }

    #[\Override]
    public function usesBackslashEscapes(): bool
    {
        return true;
    }
}
