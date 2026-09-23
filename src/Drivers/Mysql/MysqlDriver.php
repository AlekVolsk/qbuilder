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
    public const string INSERT_ROW_ALIAS = 'new';

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

    /**
     * Escape string value for a single-quoted literal.
     *
     * The quote is doubled, so the literal cannot be closed early even with NO_BACKSLASH_ESCAPES.
     * Backslash is doubled for the default sql_mode; under NO_BACKSLASH_ESCAPES it stays doubled in data.
     * Control characters are valid inside a literal and are passed as is.
     *
     * @param string $value Value to escape
     *
     * @return string Escaped value
     */
    public function escapeValue(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "''"], $value);
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

    #[\Override]
    public function usesBackslashEscapes(): bool
    {
        return true;
    }

    /**
     * Does server support INSERT ... VALUES (...) AS alias (MySQL 8.0.19+, not MariaDB).
     *
     * Unknown version gives false: VALUES(field) works on every MySQL and MariaDB version.
     */
    public function supportsInsertRowAlias(): bool
    {
        $version = $this->getServerVersion();

        if ('' === $version || false !== stripos($version, 'mariadb')) {
            return false;
        }

        if (1 !== preg_match('/^\d+\.\d+\.\d+/', $version, $matches)) {
            return false;
        }

        return version_compare($matches[0], '8.0.19', '>=');
    }

    #[\Override]
    public function buildIndexHints(array $hints): string
    {
        $sql = '';

        foreach ($hints as $hint) {
            $scope = '' === $hint['for'] ? '' : ' FOR '.$hint['for'];
            $indexes = implode(', ', array_map($this->quoteName(...), $hint['indexes']));
            $sql .= ' '.$hint['type'].' INDEX'.$scope.' ('.$indexes.')';
        }

        return $sql;
    }
}
