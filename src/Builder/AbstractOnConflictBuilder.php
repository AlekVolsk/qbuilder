<?php

declare(strict_types=1);

namespace QBuilder\Builder;

use QBuilder\Services\SqlSecurity;
use QBuilder\Traits\ExpressionsTrait;

/**
 * Base for conflict builders appended to an INSERT: conflict target, EXCLUDED reference and expressions
 * shared by ON CONFLICT (PostgreSQL, SQLite) and ON DUPLICATE KEY UPDATE (MySQL).
 *
 * @internal
 */
abstract class AbstractOnConflictBuilder extends AbstractConflictBuilder
{
    use ExpressionsTrait;

    /** @var array<int,string> */
    protected array $conflictTargets = [];

    /**
     * Specify conflict fields.
     *
     * @param array<int,string> $fields Fields by which conflict is determined
     */
    public function conflictTarget(array $fields): self
    {
        $this->conflictTargets = array_map(
            fn ($field): string => $this->quoteField(SqlSecurity::validateFieldName($field)),
            $fields
        );

        return $this;
    }

    #[\Override]
    public function excluded(string $field, string $excludedField = ''): self
    {
        $validatedField = SqlSecurity::validateFieldName($field);
        $sourceField = '' === $excludedField ? $field : $excludedField;
        $validatedSourceField = SqlSecurity::validateFieldName($sourceField);

        $this->updates[] = $this->quoteField($validatedField)
            . ' = ' . $this->formatExcludedReference($this->quoteField($validatedSourceField));

        return $this;
    }

    /**
     * Format reference to the value proposed for insertion.
     *
     * PostgreSQL/SQLite: EXCLUDED.field. MySQL: VALUES(field) or row alias field.
     *
     * @param string $quotedField Quoted field name
     *
     * @return string SQL reference
     */
    protected function formatExcludedReference(string $quotedField): string
    {
        return 'EXCLUDED.' . $quotedField;
    }
}
