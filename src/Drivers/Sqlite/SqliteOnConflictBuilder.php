<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Sqlite;

use QBuilder\Builder\AbstractOnConflictDoUpdateBuilder;

/**
 * ON CONFLICT ... DO UPDATE builder for SQLite; the conflict target may be omitted.
 *
 * @example
 * $qb->insert('users')
 *     ->insertRow(['email' => $email, 'name' => $userName])
 *     ->insertConflictHandler(
 *         $qb->conflictBuilder()
 *             ->conflictTarget(['email'])
 *             ->set('name', $userName)
 *             ->sqlFunction('updated_at', "datetime('now')")
 *     );
 *
 * @internal
 */
final class SqliteOnConflictBuilder extends AbstractOnConflictDoUpdateBuilder {}
