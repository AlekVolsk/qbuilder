<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Clickhouse;

use QBuilder\Drivers\AbstractSqlBuilder;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\Services\SqlSecurity;

/**
 * SQL query builder for ClickHouse.
 *
 * Responsible for forming final SQL from query settings
 * taking into account ClickHouse specifics.
 *
 * ClickHouse limitations:
 * - UPDATE/DELETE are ALTER TABLE operations (slow, not for OLTP)
 * - No recursive CTE
 * - No ON DUPLICATE KEY UPDATE / ON CONFLICT
 * - No stored procedures
 * - LIMIT offset, count (different syntax)
 */
class ClickhouseSqlBuilder extends AbstractSqlBuilder
{
    #[\Override]
    public function buildUpdate(): string
    {
        $fromTable = $this->queryBuilder->getFromTable();
        $updateData = $this->queryBuilder->getUpdateData();

        if ([] === $updateData) {
            throw new UnsupportedFeatureException('UPDATE requires data to update');
        }

        $sql = 'ALTER TABLE '.$this->driver->quoteName($fromTable)
            ."\nUPDATE ".$this->buildUpdateSets($updateData);

        $whereClause = $this->buildWhereClause();

        if ('' === $whereClause) {
            throw new UnsupportedFeatureException(
                'ClickHouse ALTER TABLE UPDATE requires WHERE clause for safety. '
                    .'This is a slow mutation operation, not suitable for frequent updates.'
            );
        }

        return $sql.$whereClause;
    }

    #[\Override]
    public function buildDelete(): string
    {
        $fromTable = $this->queryBuilder->getFromTable();
        $sql = 'ALTER TABLE '.$this->driver->quoteName($fromTable)."\nDELETE";

        $whereClause = $this->buildWhereClause();

        if ('' === $whereClause) {
            throw new UnsupportedFeatureException(
                'ClickHouse ALTER TABLE DELETE requires WHERE clause for safety. '
                    .'This is a slow mutation operation, not suitable for frequent deletes.'
            );
        }

        return $sql.$whereClause;
    }

    #[\Override]
    public function buildProcedure(): string
    {
        throw new UnsupportedFeatureException('ClickHouse does not support stored procedures');
    }

    #[\Override]
    protected function buildFromClause(): string
    {
        $fromTable = $this->queryBuilder->getFromTable();

        if ('' === $fromTable || '0' === $fromTable) {
            return '';
        }

        $sql = "\nFROM ";

        if ($this->queryBuilder->isFromSubquery()) {
            $sql .= $fromTable;
        } else {
            $sql .= $this->driver->quoteName($fromTable);

            if ($this->queryBuilder->isFromFinal()) {
                $sql .= ' FINAL';
            }
        }

        $fromAlias = $this->queryBuilder->getFromAlias();

        if ('' !== $fromAlias && '0' !== $fromAlias) {
            $sql .= ' AS '.$this->driver->quoteName($fromAlias);
        }

        return $sql;
    }

    #[\Override]
    protected function buildInsertRows(string $sql, array $insertRows, array $insertFields): string
    {
        $validatedFields = array_map(
            fn ($f): string => $this->driver->quoteName(SqlSecurity::validateFieldName($f)),
            $insertFields
        );
        $sql .= ' ('.implode(', ', $validatedFields).')';
        $sql .= "\nVALUES ";

        $allRows = [];

        foreach ($insertRows as $row) {
            $valuePlaceholders = [];

            foreach ($insertFields as $field) {
                $value = $row[$field] ?? null;
                $valuePlaceholders[] = $this->driver->formatValue($value);
            }
            $allRows[] = '('.implode(', ', $valuePlaceholders).')';
        }

        $sql .= implode(",\n", $allRows);

        return $sql;
    }
}
