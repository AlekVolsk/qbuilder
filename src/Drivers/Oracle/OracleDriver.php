<?php

namespace QBuilder\Drivers\Oracle;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Drivers\AbstractDriver;
use QBuilder\Drivers\SqlBuilderInterface;
use QBuilder\QueryBuilder;

/**
 * Oracle driver for QueryBuilder.
 *
 * Implements specific SQL query building logic for Oracle Database.
 */
class OracleDriver extends AbstractDriver
{
    public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface
    {
        return new OracleSqlBuilder($queryBuilder, $this);
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

    public function escapeLikePattern(string $pattern): string
    {
        $pattern = str_replace('\\', '\\\\', $pattern);
        $pattern = str_replace('%', '\%', $pattern);
        $pattern = str_replace('_', '\_', $pattern);

        return str_replace('[', '\[', $pattern);
    }

    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string
    {
        $rowsClause = $withTies ? ' ROWS WITH TIES' : ' ROWS ONLY';

        if (null !== $offset && $offset > 0) {
            return "\nOFFSET ".$offset." ROWS\nFETCH NEXT ".$limit.$rowsClause;
        }

        return "\nFETCH FIRST ".$limit.$rowsClause;
    }

    public function getName(): string
    {
        return 'oracle';
    }

    /**
     * Create MERGE builder for handling INSERT conflicts.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     */
    public function createMergeBuilder(QueryBuilder $queryBuilder): OracleMergeBuilder
    {
        return new OracleMergeBuilder($queryBuilder, $this);
    }

    public function createConflictBuilder(QueryBuilder $queryBuilder): ConflictBuilderInterface
    {
        return $this->createMergeBuilder($queryBuilder);
    }

    protected function transformIdentifier(string $identifier): string
    {
        return strtoupper($identifier);
    }
}
