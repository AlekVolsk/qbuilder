<?php

namespace QBuilder\Drivers\Pgsql;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Drivers\AbstractDriver;
use QBuilder\Drivers\SqlBuilderInterface;
use QBuilder\QueryBuilder;

/**
 * PostgreSQL driver for QueryBuilder.
 *
 * Implements specific SQL query building logic for PostgreSQL.
 */
class PgsqlDriver extends AbstractDriver
{
    public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface
    {
        return new PgsqlSqlBuilder($queryBuilder, $this);
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
        return str_replace(
            ['\\', "'"],
            ['\\\\', "''"],
            $value
        );
    }

    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string
    {
        if ($withTies) {
            return "\nFETCH FIRST ".$limit.' ROWS WITH TIES';
        }

        $sql = "\nLIMIT ".$limit;

        if (null !== $offset && $offset > 0) {
            $sql .= "\nOFFSET ".$offset;
        }

        return $sql;
    }

    public function getName(): string
    {
        return 'pgsql';
    }

    /**
     * Create ON CONFLICT ... DO UPDATE builder.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     */
    public function createOnConflictBuilder(QueryBuilder $queryBuilder): PgsqlOnConflictBuilder
    {
        return new PgsqlOnConflictBuilder($queryBuilder, $this);
    }

    public function createConflictBuilder(QueryBuilder $queryBuilder): ConflictBuilderInterface
    {
        return $this->createOnConflictBuilder($queryBuilder);
    }
}
