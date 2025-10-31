<?php

namespace QBuilder\Builder;

use QBuilder\Services\SqlSecurity;
use QBuilder\Traits\ExpressionsTrait;

/**
 * Abstract base class for MERGE builders (MSSQL, Oracle).
 *
 * Provides common functionality for MERGE statement:
 * - MERGE INTO table USING (VALUES ...) ON conflict_fields
 * - WHEN MATCHED THEN UPDATE SET ...
 * - WHEN NOT MATCHED THEN INSERT ...
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
            .' = source.'.$this->quoteField($validatedSourceField);

        return $this;
    }

    /**
     * Build complete MERGE statement.
     *
     * @return string Complete MERGE SQL
     */
    public function build(): string
    {
        if ([] === $this->updates || [] === $this->conflictFields) {
            return '';
        }

        $table = $this->queryBuilder->getFromTable();
        $insertRows = $this->queryBuilder->getInsertRows();
        $insertFields = $this->queryBuilder->getInsertFields();

        if ([] === $insertRows || [] === $insertFields) {
            return '';
        }

        $quotedTable = $this->driver->quoteName($table);
        $sql = "MERGE INTO {$quotedTable} AS target\nUSING (VALUES ";

        $allRows = [];

        foreach ($insertRows as $row) {
            $valuePlaceholders = [];

            foreach ($insertFields as $field) {
                $value = $row[$field] ?? null;

                if (null === $value) {
                    $valuePlaceholders[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $valuePlaceholders[] = $value;
                } else {
                    $valuePlaceholders[] = $this->driver->quoteValue((string) $value);
                }
            }
            $allRows[] = '('.implode(', ', $valuePlaceholders).')';
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

        $sql .= "\nWHEN MATCHED THEN\n  UPDATE SET ".implode(', ', $this->updates);

        $insertFieldsList = [];
        $sourceFieldsList = [];

        foreach ($insertFields as $field) {
            $quotedField = $this->driver->quoteName(SqlSecurity::validateFieldName($field));
            $insertFieldsList[] = $quotedField;
            $sourceFieldsList[] = "source.{$quotedField}";
        }

        $sql .= "\nWHEN NOT MATCHED THEN\n  INSERT (".implode(', ', $insertFieldsList).')';
        $sql .= "\n  VALUES (".implode(', ', $sourceFieldsList).');';

        return $sql;
    }
}
