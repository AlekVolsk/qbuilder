<?php

namespace QBuilder\Drivers\Sqlite;

use QBuilder\Builder\AbstractOnConflictBuilder;
use QBuilder\Drivers\DriverInterface;
use QBuilder\QueryBuilder;

/**
 * ON CONFLICT ... DO UPDATE builder for SQLite.
 *
 * SQLite-specific syntax for handling conflicts on INSERT (UPSERT).
 * Similar to PostgreSQL ON CONFLICT.
 *
 * @example
 * // Simple usage (values are safely escaped)
 * $builder = SqliteOnConflictBuilder::create($db, $driver)
 *     ->conflictTarget(['email'])  // Specify conflict fields
 *     ->set('name', $userName)  // Safe: automatically quoted
 *     ->sqlFunction('updated_at', 'datetime(\'now\')');
 *
 * // Using EXCLUDED reference
 * $builder = SqliteOnConflictBuilder::create($db, $driver)
 *     ->conflictTarget(['email'])
 *     ->excluded('email')  // Generates: email = EXCLUDED.email
 *     ->increment('view_count', 1);
 */
class SqliteOnConflictBuilder extends AbstractOnConflictBuilder
{
    /**
     * Create new builder instance.
     *
     * @param QueryBuilder    $queryBuilder Parent QueryBuilder
     * @param DriverInterface $driver       SQLite driver
     */
    public static function create(QueryBuilder $queryBuilder, DriverInterface $driver): self
    {
        return new self($queryBuilder, $driver);
    }

    #[\Override]
    protected function getExcludedKeyword(): string
    {
        return 'EXCLUDED';
    }

    #[\Override]
    protected function buildConflictClause(): string
    {
        if ([] === $this->conflictTargets) {
            return "\nON CONFLICT";
        }

        return "\nON CONFLICT (".implode(', ', $this->conflictTargets).')';
    }

    #[\Override]
    protected function buildUpdatePrefix(): string
    {
        return 'DO UPDATE SET';
    }
}
