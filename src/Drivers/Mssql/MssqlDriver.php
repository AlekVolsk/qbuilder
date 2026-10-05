<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mssql;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Drivers\AbstractDriver;
use QBuilder\Drivers\SqlBuilderInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * MS SQL Server driver for QueryBuilder.
 *
 * Implements specific SQL query building logic for Microsoft SQL Server.
 *
 * @internal
 */
final class MssqlDriver extends AbstractDriver
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

    #[\Override]
    public function getLikeEscapeClause(): string
    {
        return '';
    }

    /**
     * OFFSET ... FETCH is part of the ORDER BY clause in T-SQL.
     */
    #[\Override]
    public function limitRequiresOrderBy(): bool
    {
        return true;
    }

    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string
    {
        $rowsClause = $withTies ? ' ROWS WITH TIES' : ' ROWS ONLY';

        if (null !== $offset && $offset > 0) {
            return "\nOFFSET " . $offset . " ROWS\nFETCH NEXT " . $limit . $rowsClause;
        }

        return "\nOFFSET 0 ROWS\nFETCH NEXT " . $limit . $rowsClause;
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

    /**
     * Build table hint WITH (INDEX(...)) from FORCE INDEX hints.
     *
     * MS SQL Server index hint always forces the index and has no scope,
     * so USE INDEX, IGNORE INDEX and FOR ... have no equivalent.
     *
     * @param list<array{type:string,indexes:list<string>,for:string}> $hints Index hints
     *
     * @return string SQL fragment with leading space, or empty string without hints
     *
     * @throws UnsupportedFeatureException If a hint other than FORCE INDEX without scope is requested
     */
    #[\Override]
    public function buildIndexHints(array $hints): string
    {
        $indexes = [];

        foreach ($hints as $hint) {
            if (QbConsts::INDEX_FORCE !== $hint['type'] || '' !== $hint['for']) {
                throw new UnsupportedFeatureException(
                    'MS SQL Server supports only forceIndex() without scope: its INDEX table hint always forces '
                        . 'the index. ' . $hint['type'] . ' INDEX' . ('' === $hint['for'] ? '' : ' FOR ' . $hint['for'])
                        . ' has no equivalent'
                );
            }

            array_push($indexes, ...$hint['indexes']);
        }

        if ([] === $indexes) {
            return '';
        }

        return ' WITH (INDEX(' . implode(', ', array_map($this->quoteName(...), $indexes)) . '))';
    }
}
