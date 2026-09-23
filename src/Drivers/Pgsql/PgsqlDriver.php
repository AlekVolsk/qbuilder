<?php

declare(strict_types=1);

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

    /**
     * Quote string value.
     *
     * A value with a backslash becomes an escape string constant E'...', where the backslash
     * is an escape character regardless of standard_conforming_strings, so \\ always means one backslash.
     *
     * @param string $value Value to quote
     *
     * @return string Quoted value
     */
    #[\Override]
    public function quoteValue(string $value): string
    {
        $quoted = parent::quoteValue($value);

        return str_contains($value, '\\') ? 'E'.$quoted : $quoted;
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

    #[\Override]
    protected function formatBool(bool $value): string
    {
        return $value ? 'TRUE' : 'FALSE';
    }
}
