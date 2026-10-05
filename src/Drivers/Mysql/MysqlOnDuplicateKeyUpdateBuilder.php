<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mysql;

use QBuilder\Builder\AbstractOnConflictBuilder;

/**
 * ON DUPLICATE KEY UPDATE builder for MySQL.
 *
 * build() returns only the assignments: the INSERT builder places the row alias and the
 * ON DUPLICATE KEY UPDATE keywords after VALUES (see MysqlSqlBuilder::buildInsertConflictSuffix()).
 * MySQL detects the conflict by unique keys, so conflictTarget() has no effect.
 *
 * @example
 * $qb->insert('users')
 *     ->insertRow(['email' => $email, 'name' => $userName, 'view_count' => 1])
 *     ->insertConflictHandler(
 *         $qb->conflictBuilder()
 *             ->excluded('name')          // `name` = VALUES(`name`) or `new`.`name`
 *             ->increment('view_count')   // `view_count` = `view_count` + 1
 *     );
 *
 * @internal
 */
final class MysqlOnDuplicateKeyUpdateBuilder extends AbstractOnConflictBuilder
{
    #[\Override]
    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter -- MySQL resolves conflicts by unique keys
    public function conflictTarget(array $fields): self
    {
        return $this;
    }

    #[\Override]
    public function build(): string
    {
        if ([] === $this->updates) {
            return '';
        }

        return $this->buildUpdates();
    }

    /**
     * Row alias reference `` `new`.`field` `` when the server supports it (MySQL 8.0.19+),
     * `` VALUES(`field`) `` otherwise.
     *
     * @param string $quotedField Quoted field name
     *
     * @return string SQL reference
     */
    #[\Override]
    protected function formatExcludedReference(string $quotedField): string
    {
        $driver = $this->getDriver();

        if ($driver instanceof MysqlDriver && $driver->supportsInsertRowAlias()) {
            return $driver->quoteName(MysqlDriver::INSERT_ROW_ALIAS) . '.' . $quotedField;
        }

        return 'VALUES(' . $quotedField . ')';
    }
}
