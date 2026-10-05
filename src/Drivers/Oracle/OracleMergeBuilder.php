<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Oracle;

use QBuilder\Builder\AbstractMergeBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * MERGE builder for Oracle Database.
 *
 * Oracle-specific syntax for handling duplicates on INSERT using MERGE statement.
 * Allows building complex MERGE expressions with WHEN MATCHED and WHEN NOT MATCHED.
 *
 * MERGE is built from the rows already added to INSERT, so insertRow() goes before insertConflictHandler().
 *
 * @example
 * $qb->insert('users')
 *     ->insertRow(['email' => $email, 'name' => $userName, 'view_count' => 1])
 *     ->insertConflictHandler(
 *         $qb->conflictBuilder()
 *             ->conflictTarget(['email'])
 *             ->set('name', $userName)
 *             ->increment('view_count')
 *             ->sqlFunction('updated_at', 'SYSDATE')
 *     );
 *
 * @internal
 */
final class OracleMergeBuilder extends AbstractMergeBuilder
{
    #[\Override]
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
        $sql = "MERGE INTO {$quotedTable} target\nUSING (";

        $rowSelects = [];

        foreach ($insertRows as $row) {
            $selects = [];

            foreach ($insertFields as $field) {
                $selects[] = $this->driver->formatValue($row[$field] ?? null) . ' AS '
                    . $this->driver->quoteName($field);
            }

            $rowSelects[] = 'SELECT ' . implode(', ', $selects) . ' FROM DUAL';
        }

        $sql .= implode("\nUNION ALL ", $rowSelects);

        $sql .= ') source ON (';

        $matchConditions = [];

        foreach ($this->conflictFields as $field) {
            $quotedField = $this->driver->quoteName($field);
            $matchConditions[] = "target.{$quotedField} = source.{$quotedField}";
        }
        $sql .= implode(' AND ', $matchConditions);

        $sql .= ")\nWHEN MATCHED THEN\n  UPDATE SET " . $this->buildUpdates();

        $insertFieldsList = [];
        $sourceFieldsList = [];

        foreach ($insertFields as $field) {
            $quotedField = $this->driver->quoteName(SqlSecurity::validateFieldName($field));
            $insertFieldsList[] = $quotedField;
            $sourceFieldsList[] = "source.{$quotedField}";
        }

        $sql .= "\nWHEN NOT MATCHED THEN\n  INSERT (" . implode(', ', $insertFieldsList) . ')';
        $sql .= "\n  VALUES (" . implode(', ', $sourceFieldsList) . ')';

        return $sql;
    }
}
