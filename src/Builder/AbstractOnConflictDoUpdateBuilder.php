<?php

declare(strict_types=1);

namespace QBuilder\Builder;

/**
 * ON CONFLICT [(target)] DO UPDATE SET builder shared by PostgreSQL and SQLite.
 *
 * @internal
 */
abstract class AbstractOnConflictDoUpdateBuilder extends AbstractOnConflictBuilder
{
    #[\Override]
    public function build(): string
    {
        if ([] === $this->updates) {
            return '';
        }

        return $this->buildConflictClause() . "\nDO UPDATE SET " . $this->buildUpdates();
    }

    /**
     * ON CONFLICT with the conflict target; without a target it matches any uniqueness constraint.
     */
    protected function buildConflictClause(): string
    {
        if ([] === $this->conflictTargets) {
            return "\nON CONFLICT";
        }

        return "\nON CONFLICT (" . implode(', ', $this->conflictTargets) . ')';
    }
}
