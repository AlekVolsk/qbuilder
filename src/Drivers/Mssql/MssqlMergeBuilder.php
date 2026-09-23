<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mssql;

use QBuilder\Builder\AbstractMergeBuilder;
use QBuilder\Drivers\DriverInterface;
use QBuilder\QueryBuilder;

/**
 * MERGE builder for MS SQL Server.
 *
 * MS SQL Server-specific syntax for handling duplicates on INSERT using MERGE statement.
 * Allows building complex MERGE expressions with WHEN MATCHED and WHEN NOT MATCHED.
 *
 * @example
 * // Basic MERGE
 * $builder = MssqlMergeBuilder::create($qb, $driver)
 *     ->conflictTarget(['email'])
 *     ->set('name', $userName)
 *     ->set('updated_at', 'GETDATE()');
 *
 * // With increment
 * $builder = MssqlMergeBuilder::create($qb, $driver)
 *     ->conflictTarget(['user_id', 'product_id'])
 *     ->increment('view_count', 1)
 *     ->sqlFunction('last_viewed', 'GETDATE()');
 */
class MssqlMergeBuilder extends AbstractMergeBuilder
{
    /**
     * Create new builder instance.
     *
     * @param QueryBuilder    $queryBuilder Parent QueryBuilder
     * @param DriverInterface $driver       MSSQL driver
     */
    public static function create(QueryBuilder $queryBuilder, DriverInterface $driver): self
    {
        return new self($queryBuilder, $driver);
    }
}
