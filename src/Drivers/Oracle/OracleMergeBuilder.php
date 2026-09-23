<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Oracle;

use QBuilder\Builder\AbstractMergeBuilder;
use QBuilder\Drivers\DriverInterface;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * MERGE builder for Oracle Database.
 *
 * Oracle-specific syntax for handling duplicates on INSERT using MERGE statement.
 * Allows building complex MERGE expressions with WHEN MATCHED and WHEN NOT MATCHED.
 *
 * @example
 * // Basic MERGE
 * $builder = OracleMergeBuilder::create($qb, $driver)
 *     ->conflictTarget(['email'])
 *     ->set('name', $userName)
 *     ->set('updated_at', 'SYSDATE');
 *
 * // With increment
 * $builder = OracleMergeBuilder::create($qb, $driver)
 *     ->conflictTarget(['user_id', 'product_id'])
 *     ->increment('view_count', 1)
 *     ->sqlFunction('last_viewed', 'SYSDATE');
 */
class OracleMergeBuilder extends AbstractMergeBuilder
{
    /**
     * Create new builder instance.
     *
     * @param QueryBuilder    $queryBuilder Parent QueryBuilder
     * @param DriverInterface $driver       Oracle driver
     */
    public static function create(QueryBuilder $queryBuilder, DriverInterface $driver): self
    {
        return new self($queryBuilder, $driver);
    }

    #[\Override]
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
        $sql = "MERGE INTO {$quotedTable} target\nUSING (";

        if (1 === \count($insertRows)) {
            $sql .= 'SELECT ';
            $row = $insertRows[0];
            $selects = [];

            foreach ($insertFields as $field) {
                $value = $row[$field] ?? null;

                if (null === $value) {
                    $selects[] = 'NULL AS '.$this->driver->quoteName($field);
                } elseif (is_numeric($value)) {
                    $selects[] = $value.' AS '.$this->driver->quoteName($field);
                } else {
                    $selects[] = $this->driver->quoteValue((string) $value).' AS '
                        .$this->driver->quoteName($field);
                }
            }
            $sql .= implode(', ', $selects).' FROM DUAL';
        } else {
            $allSelects = [];

            foreach ($insertRows as $index => $row) {
                $selects = [];

                foreach ($insertFields as $field) {
                    $value = $row[$field] ?? null;

                    if (null === $value) {
                        $selects[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $selects[] = $value;
                    } else {
                        $selects[] = $this->driver->quoteValue((string) $value);
                    }
                }

                $prefix = 0 === $index ? 'SELECT ' : 'UNION ALL SELECT ';
                $allSelects[] = $prefix.implode(', ', $selects).' FROM DUAL';
            }
            $sql .= implode("\n", $allSelects);
        }

        $sql .= ') source ON (';

        $matchConditions = [];

        foreach ($this->conflictFields as $field) {
            $quotedField = $this->driver->quoteName($field);
            $matchConditions[] = "target.{$quotedField} = source.{$quotedField}";
        }
        $sql .= implode(' AND ', $matchConditions);

        $sql .= ")\nWHEN MATCHED THEN\n  UPDATE SET ".implode(', ', $this->updates);

        $insertFieldsList = [];
        $sourceFieldsList = [];

        foreach ($insertFields as $field) {
            $quotedField = $this->driver->quoteName(SqlSecurity::validateFieldName($field));
            $insertFieldsList[] = $quotedField;
            $sourceFieldsList[] = "source.{$quotedField}";
        }

        $sql .= "\nWHEN NOT MATCHED THEN\n  INSERT (".implode(', ', $insertFieldsList).')';
        $sql .= "\n  VALUES (".implode(', ', $sourceFieldsList).')';

        return $sql;
    }
}
