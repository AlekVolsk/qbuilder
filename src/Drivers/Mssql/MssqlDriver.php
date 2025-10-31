<?php

namespace QBuilder\Drivers\Mssql;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Drivers\AbstractDriver;
use QBuilder\Drivers\SqlBuilderInterface;
use QBuilder\QueryBuilder;

/**
 * MS SQL Server driver for QueryBuilder.
 *
 * Implements specific SQL query building logic for Microsoft SQL Server.
 */
class MssqlDriver extends AbstractDriver
{
    public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface
    {
        return new MssqlSqlBuilder($queryBuilder, $this);
    }

    public function getIdentifierQuote(): string
    {
        return '[';
    }

    public function getIdentifierCloseQuote(): string
    {
        return ']';
    }

    public function getValueQuote(): string
    {
        return "'";
    }

    public function escapeValue(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    public function escapeLikePattern(string $pattern): string
    {
        $pattern = str_replace('[', '[[]', $pattern);
        $pattern = str_replace('%', '[%]', $pattern);

        return str_replace('_', '[_]', $pattern);
    }

    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string
    {
        $rowsClause = $withTies ? ' ROWS WITH TIES' : ' ROWS ONLY';

        if (null !== $offset && $offset > 0) {
            return "\nOFFSET ".$offset." ROWS\nFETCH NEXT ".$limit.$rowsClause;
        }

        return "\nOFFSET 0 ROWS\nFETCH NEXT ".$limit.$rowsClause;
    }

    public function getName(): string
    {
        return 'mssql';
    }

    /**
     * Create MERGE builder for handling INSERT conflicts.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     */
    public function createMergeBuilder(QueryBuilder $queryBuilder): MssqlMergeBuilder
    {
        return new MssqlMergeBuilder($queryBuilder, $this);
    }

    public function createConflictBuilder(QueryBuilder $queryBuilder): ConflictBuilderInterface
    {
        return $this->createMergeBuilder($queryBuilder);
    }
}
