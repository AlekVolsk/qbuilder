<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mssql;

use QBuilder\Builder\AbstractMergeBuilder;

/**
 * MERGE builder for MS SQL Server.
 *
 * MS SQL Server-specific syntax for handling duplicates on INSERT using MERGE statement.
 * Allows building complex MERGE expressions with WHEN MATCHED and WHEN NOT MATCHED.
 *
 * MERGE is built from the rows already added to INSERT, so insertRow() goes before insertConflictHandler().
 *
 * @example
 * $qb->insert('users')
 *     ->insertRow(['email' => $email, 'name' => $userName, 'view_count' => 1])
 *     ->insertConflictHandler(
 *         $qb->conflictBuilder()
 *             ->conflictTarget(['email'])
 *             ->set('name', $userName)
 *             ->increment('view_count')
 *             ->sqlFunction('updated_at', 'GETDATE()')
 *     );
 *
 * @internal
 */
final class MssqlMergeBuilder extends AbstractMergeBuilder {}
