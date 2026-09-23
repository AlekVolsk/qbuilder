<?php

declare(strict_types=1);

namespace QBuilder\Builder;

use QBuilder\Services\SqlSecurity;
use QBuilder\Traits\ExpressionsTrait;

/**
 * Abstract base class for ON CONFLICT / ON DUPLICATE KEY builders.
 *
 * Provides common functionality for:
 * - PostgreSQL: ON CONFLICT ... DO UPDATE
 * - SQLite: ON CONFLICT ... DO UPDATE
 * - MySQL: ON DUPLICATE KEY UPDATE
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
            .' = '.$this->formatExcludedReference($this->quoteField($validatedSourceField));

        return $this;
    }

    /**
     * Build final SQL.
     */
    public function build(): string
    {
        if ([] === $this->updates) {
            return '';
        }

        $conflictClause = $this->buildConflictClause();
        $sql = '' !== $conflictClause ? $conflictClause."\n" : "\n";
        $sql .= $this->buildUpdatePrefix();
        $sql .= ' '.implode(', ', $this->updates);

        return $sql;
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
        return 'EXCLUDED.'.$quotedField;
    }

    /**
     * Build the conflict clause.
     * PostgreSQL/SQLite: ON CONFLICT (fields)
     * MySQL: (empty, uses ON DUPLICATE KEY).
     */
    abstract protected function buildConflictClause(): string;

    /**
     * Build the update clause prefix.
     * PostgreSQL/SQLite: DO UPDATE SET
     * MySQL: ON DUPLICATE KEY UPDATE.
     */
    abstract protected function buildUpdatePrefix(): string;
}
