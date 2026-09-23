<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Sqlite;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Drivers\AbstractDriver;
use QBuilder\Drivers\SqlBuilderInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QueryBuilder;

/**
 * SQLite driver for QueryBuilder.
 *
 * Implements specific SQL query building logic for SQLite.
 */
class SqliteDriver extends AbstractDriver
{
    public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface
    {
        return new SqliteSqlBuilder($queryBuilder, $this);
    }

    public function getIdentifierQuote(): string
    {
        return '"';
    }

    public function getIdentifierCloseQuote(): string
    {
        return '"';
    }

    public function getValueQuote(): string
    {
        return "'";
    }

    public function escapeValue(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string
    {
        if ($withTies) {
            throw new UnsupportedFeatureException(
                'SQLite does not support LIMIT WITH TIES. '
                .'This feature is available in PostgreSQL, MS SQL Server, Oracle, and ClickHouse.'
            );
        }

        $sql = "\nLIMIT ".$limit;

        if (null !== $offset && $offset > 0) {
            $sql .= "\nOFFSET ".$offset;
        }

        return $sql;
    }

    public function getName(): string
    {
        return 'sqlite';
    }

    /**
     * Create ON CONFLICT ... DO UPDATE builder.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     */
    public function createOnConflictBuilder(QueryBuilder $queryBuilder): SqliteOnConflictBuilder
    {
        return new SqliteOnConflictBuilder($queryBuilder, $this);
    }

    public function createConflictBuilder(QueryBuilder $queryBuilder): ConflictBuilderInterface
    {
        return $this->createOnConflictBuilder($queryBuilder);
    }
}
