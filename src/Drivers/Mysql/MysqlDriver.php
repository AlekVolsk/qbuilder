<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mysql;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Drivers\AbstractDriver;
use QBuilder\Drivers\SqlBuilderInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QueryBuilder;

/**
 * MySQL driver for QueryBuilder.
 *
 * Implements specific SQL query building logic for MySQL/MariaDB.
 */
class MysqlDriver extends AbstractDriver
{
    public function getSqlBuilder(QueryBuilder $queryBuilder): SqlBuilderInterface
    {
        return new MysqlSqlBuilder($queryBuilder, $this);
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
            ['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
            ['\\\\', '\0', '\n', '\r', "\\'", '\"', '\Z'],
            $value
        );
    }

    public function getLimitSql(int $limit, ?int $offset = null, bool $withTies = false): string
    {
        if ($withTies) {
            throw new UnsupportedFeatureException(
                'MySQL does not support LIMIT WITH TIES. '
                .'This feature is available in PostgreSQL, MS SQL Server, Oracle, and ClickHouse.'
            );
        }

        if (null !== $offset && $offset > 0) {
            return "\nLIMIT ".$offset.', '.$limit;
        }

        return "\nLIMIT ".$limit;
    }

    public function getName(): string
    {
        return 'mysql';
    }

    /**
     * Create ON DUPLICATE KEY UPDATE builder.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     */
    public function createOnDuplicateKeyUpdateBuilder(QueryBuilder $queryBuilder): MysqlOnDuplicateKeyUpdateBuilder
    {
        return new MysqlOnDuplicateKeyUpdateBuilder($queryBuilder, $this);
    }

    public function createConflictBuilder(QueryBuilder $queryBuilder): ConflictBuilderInterface
    {
        return $this->createOnDuplicateKeyUpdateBuilder($queryBuilder);
    }
}
