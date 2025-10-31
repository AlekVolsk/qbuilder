<?php

namespace QBuilder\Drivers\Pgsql;

use QBuilder\Builder\AbstractOnConflictBuilder;
use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\QueryBuilder;

/**
 * ON CONFLICT ... DO UPDATE builder for PostgreSQL.
 *
 * PostgreSQL-specific syntax for handling conflicts on INSERT.
 * Analog of MySQL ON DUPLICATE KEY UPDATE.
 *
 * @example
 * // Simple usage (values are safely escaped)
 * $builder = PgsqlOnConflictBuilder::create($db, $driver)
 *     ->conflictTarget(['email'])  // Specify conflict fields
 *     ->set('name', $userName)  // Safe: automatically quoted
 *     ->sqlFunction('updated_at', 'NOW()');
 *
 * // Using EXCLUDED reference
 * $builder = PgsqlOnConflictBuilder::create($db, $driver)
 *     ->conflictTarget(['email'])
 *     ->excluded('email')  // Generates: email = EXCLUDED.email
 *     ->increment('view_count', 1);
 *
 * // Complex expressions
 * $builder = PgsqlOnConflictBuilder::create($db, $driver)
 *     ->conflictTarget(['id'])
 *     ->expression('price', 'price * 1.1')
 *     ->caseExpression('status', [
 *         'WHEN count > 10 THEN \'active\'',
 *         'ELSE \'pending\''
 *     ]);
 */
class PgsqlOnConflictBuilder extends AbstractOnConflictBuilder
{
    /**
     * Create new builder instance.
     *
     * @param QueryBuilder    $queryBuilder Parent QueryBuilder
     * @param DriverInterface $driver       PostgreSQL driver
     */
    public static function create(QueryBuilder $queryBuilder, DriverInterface $driver): self
    {
        return new self($queryBuilder, $driver);
    }

    #[\Override]
    public function build(): string
    {
        if ([] === $this->conflictTargets) {
            throw new MissingRequirementException('Conflict target must be specified for PostgreSQL ON CONFLICT');
        }

        if ([] === $this->updates) {
            return "\nON CONFLICT (".implode(', ', $this->conflictTargets).') DO NOTHING';
        }

        return parent::build();
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
            throw new MissingRequirementException(
                'PostgreSQL ON CONFLICT requires conflict target fields. Use ->conflictTarget([...]) to specify them.'
            );
        }

        return "\nON CONFLICT (".implode(', ', $this->conflictTargets).')';
    }

    #[\Override]
    protected function buildUpdatePrefix(): string
    {
        return 'DO UPDATE SET';
    }
}
