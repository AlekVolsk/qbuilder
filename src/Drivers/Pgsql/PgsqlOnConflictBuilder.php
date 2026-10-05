<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Pgsql;

use QBuilder\Builder\AbstractOnConflictDoUpdateBuilder;
use QBuilder\Exceptions\MissingRequirementException;

/**
 * ON CONFLICT ... DO UPDATE builder for PostgreSQL: the conflict target is required,
 * without updates the conflict is skipped with DO NOTHING.
 *
 * @example
 * $qb->insert('users')
 *     ->insertRow(['email' => $email, 'name' => $userName, 'view_count' => 1])
 *     ->insertConflictHandler(
 *         $qb->conflictBuilder()
 *             ->conflictTarget(['email'])
 *             ->excluded('name')          // "name" = EXCLUDED."name"
 *             ->increment('view_count')   // "view_count" = "users"."view_count" + 1
 *     );
 *
 * @internal
 */
final class PgsqlOnConflictBuilder extends AbstractOnConflictDoUpdateBuilder
{
    #[\Override]
    public function build(): string
    {
        if ([] === $this->conflictTargets) {
            throw new MissingRequirementException('Conflict target must be specified for PostgreSQL ON CONFLICT');
        }

        if ([] === $this->updates) {
            return "\nON CONFLICT (" . implode(', ', $this->conflictTargets) . ') DO NOTHING';
        }

        return parent::build();
    }

    /**
     * PostgreSQL resolves an unqualified column in DO UPDATE SET as ambiguous with EXCLUDED,
     * so the current value is referenced through the INSERT table.
     *
     * @throws MissingRequirementException If the INSERT table is not set yet
     */
    #[\Override]
    protected function currentValueReference(string $quotedField): string
    {
        $table = $this->queryBuilder->getFromTable();

        if ('' === $table) {
            throw new MissingRequirementException(
                'PostgreSQL ON CONFLICT needs the INSERT table: call insert() before insertConflictHandler()'
            );
        }

        return $this->getDriver()->quoteName($table) . '.' . $quotedField;
    }
}
