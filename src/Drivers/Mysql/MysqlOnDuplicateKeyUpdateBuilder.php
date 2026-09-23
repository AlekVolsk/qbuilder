<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mysql;

use QBuilder\Builder\AbstractOnConflictBuilder;
use QBuilder\Drivers\DriverInterface;
use QBuilder\QueryBuilder;

/**
 * ON DUPLICATE KEY UPDATE builder for MySQL.
 *
 * MySQL-specific syntax for handling duplicates on INSERT.
 * Allows building complex UPDATE expressions using:
 * - Simple values (automatically quoted)
 * - SQL functions (NOW(), CURDATE(), etc.)
 * - VALUES() to reference inserted values
 * - Arithmetic expressions (count + 1, price * 1.1, etc.)
 * - Conditional expressions (CASE WHEN)
 *
 * @example
 * // Simple usage (values are safely escaped)
 * $builder = MysqlOnDuplicateKeyUpdateBuilder::create($db, $driver)
 *     ->set('name', $userName)  // Safe: automatically quoted
 *     ->increment('count', 1);
 *
 * // Using VALUES() reference
 * $builder = MysqlOnDuplicateKeyUpdateBuilder::create($db, $driver)
 *     ->excluded('email')  // Generates: email = VALUES(email)
 *     ->increment('view_count', 1)
 *     ->sqlFunction('updated_at', 'NOW()');
 *
 * // Complex expressions
 * $builder = MysqlOnDuplicateKeyUpdateBuilder::create($db, $driver)
 *     ->expression('price', 'price * 1.1')
 *     ->caseExpression('status', [
 *         'WHEN count > 10 THEN "active"',
 *         'ELSE "pending"'
 *     ]);
 */
class MysqlOnDuplicateKeyUpdateBuilder extends AbstractOnConflictBuilder
{
    /**
     * Create new builder instance.
     *
     * @param QueryBuilder    $queryBuilder Parent QueryBuilder
     * @param DriverInterface $driver       MySQL driver
     */
    public static function create(QueryBuilder $queryBuilder, DriverInterface $driver): self
    {
        return new self($queryBuilder, $driver);
    }

    #[\Override]
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

        return implode(', ', $this->updates);
    }

    #[\Override]
    protected function getExcludedKeyword(): string
    {
        return 'VALUES';
    }

    #[\Override]
    protected function buildConflictClause(): string
    {
        return '';
    }

    #[\Override]
    protected function buildUpdatePrefix(): string
    {
        return 'ON DUPLICATE KEY UPDATE';
    }
}
