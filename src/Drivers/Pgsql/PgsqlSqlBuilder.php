<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Pgsql;

use QBuilder\Drivers\AbstractSqlBuilder;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * SQL query builder for PostgreSQL.
 *
 * Responsible for forming final SQL from query settings
 * taking into account PostgreSQL specifics.
 *
 * @internal
 */
final class PgsqlSqlBuilder extends AbstractSqlBuilder
{
    #[\Override]
    public function buildInsert(): string
    {
        $sql = 'INSERT INTO ' . $this->driver->quoteName($this->queryBuilder->getFromTable());
        $insertData = $this->queryBuilder->getInsertData();

        if (($insertData['_subquery'] ?? null) instanceof QueryBuilder) {
            return $this->buildInsertFromSubquery($sql, $insertData['_subquery'], $insertData['_fields'] ?? []);
        }

        $insertFields = $this->queryBuilder->getInsertFields();
        $quotedFields = array_map(
            fn (string $field): string => $this->driver->quoteName(SqlSecurity::validateFieldName($field)),
            $insertFields
        );

        $valuesSets = [];

        foreach ($this->queryBuilder->getInsertRows() as $row) {
            $values = [];

            foreach ($insertFields as $field) {
                $values[] = $this->driver->formatValue($row[$field] ?? null);
            }

            $valuesSets[] = '(' . implode(', ', $values) . ')';
        }

        $sql .= ' (' . implode(', ', $quotedFields) . ")\nVALUES\n" . implode(",\n", $valuesSets);

        $onConflict = $this->queryBuilder->getInsertConflictData();

        if ('' !== $onConflict) {
            $sql .= "\n" . $onConflict;
        }

        return $sql;
    }

    #[\Override]
    public function buildProcedure(): string
    {
        $procedureName = $this->queryBuilder->getProcedureName();

        $sql = 'CALL ' . $this->driver->quoteName($procedureName);

        $params = $this->queryBuilder->getProcedureParams();

        $formattedParams = [];

        foreach ($params as $param) {
            $formattedParams[] = $this->driver->formatValue($param);
        }

        return $sql . ('(' . implode(', ', $formattedParams) . ')');
    }
}
