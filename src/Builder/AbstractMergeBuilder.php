<?php

declare(strict_types=1);

namespace QBuilder\Builder;

use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\Services\SqlSecurity;
use QBuilder\Traits\ExpressionsTrait;

/**
 * Abstract base class for MERGE builders (MSSQL, Oracle).
 *
 * Provides common functionality for MERGE statement:
 * - MERGE INTO table USING (VALUES ...) ON conflict_fields
 * - WHEN MATCHED THEN UPDATE SET ...
 * - WHEN NOT MATCHED THEN INSERT ...
 *
 * @internal
 */
abstract class AbstractMergeBuilder extends AbstractConflictBuilder
{
    use ExpressionsTrait;

    #[\Override]
    public function excluded(string $field, string $excludedField = ''): self
    {
        $validatedField = SqlSecurity::validateFieldName($field);
        $sourceField = '' === $excludedField ? $field : $excludedField;
        $validatedSourceField = SqlSecurity::validateFieldName($sourceField);

        $this->updates[] = $this->quoteField($validatedField)
            . ' = source.' . $this->quoteField($validatedSourceField);

        return $this;
    }

    /**
     * Build complete MERGE statement.
     *
     * @return string Complete MERGE SQL
     */
    public function build(): string
    {
        if ([] === $this->updates) {
            return '';
        }

        $this->assertMergeInput();

        $table = $this->queryBuilder->getFromTable();
        $insertRows = $this->queryBuilder->getInsertRows();
        $insertFields = $this->queryBuilder->getInsertFields();

        $quotedTable = $this->driver->quoteName($table);
        $sql = "MERGE INTO {$quotedTable} AS target\nUSING (VALUES ";

        $allRows = [];

        foreach ($insertRows as $row) {
            $valuePlaceholders = [];

            foreach ($insertFields as $field) {
                $valuePlaceholders[] = $this->driver->formatValue($row[$field] ?? null);
            }
            $allRows[] = '(' . implode(', ', $valuePlaceholders) . ')';
        }

        $sql .= implode(",\n       ", $allRows);
        $sql .= ') AS source (';

        $quotedFields = array_map(
            fn ($f): string => $this->driver->quoteName(SqlSecurity::validateFieldName($f)),
            $insertFields
        );
        $sql .= implode(', ', $quotedFields);
        $sql .= ")\nON ";

        $matchConditions = [];

        foreach ($this->conflictFields as $field) {
            $quotedField = $this->driver->quoteName($field);
            $matchConditions[] = "target.{$quotedField} = source.{$quotedField}";
        }
        $sql .= implode(' AND ', $matchConditions);

        $sql .= "\nWHEN MATCHED THEN\n  UPDATE SET " . $this->buildUpdates();

        $insertFieldsList = [];
        $sourceFieldsList = [];

        foreach ($insertFields as $field) {
            $quotedField = $this->driver->quoteName(SqlSecurity::validateFieldName($field));
            $insertFieldsList[] = $quotedField;
            $sourceFieldsList[] = "source.{$quotedField}";
        }

        $sql .= "\nWHEN NOT MATCHED THEN\n  INSERT (" . implode(', ', $insertFieldsList) . ')';
        $sql .= "\n  VALUES (" . implode(', ', $sourceFieldsList) . ');';

        return $sql;
    }

    /**
     * The source rows carry the same column names, so an unqualified column would be ambiguous.
     */
    #[\Override]
    protected function currentValueReference(string $quotedField): string
    {
        return 'target.' . $quotedField;
    }

    /**
     * MERGE is built from the conflict target and the rows already added to INSERT.
     *
     * @throws MissingRequirementException If the conflict target or the rows are missing
     */
    protected function assertMergeInput(): void
    {
        if ([] === $this->conflictFields) {
            throw new MissingRequirementException('MERGE needs a conflict target: use conflictTarget()');
        }

        if ([] === $this->queryBuilder->getInsertFields()) {
            throw new MissingRequirementException(
                'MERGE needs the INSERT rows: call insertRow() before insertConflictHandler()'
            );
        }
    }
}
