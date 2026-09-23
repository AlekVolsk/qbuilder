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
 */
class PgsqlSqlBuilder extends AbstractSqlBuilder
{
    #[\Override]
    public function buildInsert(): string
    {
        $fromTable = $this->queryBuilder->getFromTable();
        $sql = 'INSERT INTO '.$this->driver->quoteName($fromTable);

        $insertData = $this->queryBuilder->getInsertData();
        $insertRows = $this->queryBuilder->getInsertRows();
        $insertFields = $this->queryBuilder->getInsertFields();

        if (
            [] !== $insertData
            && isset($insertData['_subquery'])
            && $insertData['_subquery'] instanceof QueryBuilder
        ) {
            $subQuery = $insertData['_subquery'];
            $fields = $insertData['_fields'] ?? [];

            if (! empty($fields) && \is_array($fields)) {
                $validatedFields = array_map(
                    fn ($f): string => $this->driver->quoteName(SqlSecurity::validateFieldName($f)),
                    $fields
                );

                $sql .= ' ('.implode(', ', $validatedFields).')';
            }

            return $sql.("\n".$subQuery->build());
        }

        if ([] !== $insertRows) {
            if ([] !== $insertFields) {
                $quotedFields = array_map(
                    fn ($field): string => $this->driver->quoteName(
                        SqlSecurity::validateFieldName($field)
                    ),
                    $insertFields
                );

                $sql .= ' ('.implode(', ', $quotedFields).")\nVALUES\n";

                $valuesSets = [];

                foreach ($insertRows as $row) {
                    $values = [];

                    foreach ($insertFields as $field) {
                        $value = $row[$field] ?? null;

                        if (null === $value) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = $this->driver->quoteValue((string) $value);
                        }
                    }
                    $valuesSets[] = '('.implode(', ', $values).')';
                }

                $sql .= implode(",\n", $valuesSets);

                $onConflict = $this->queryBuilder->getInsertConflictData();

                if ('' !== $onConflict && '0' !== $onConflict) {
                    $sql .= "\n".$onConflict;
                }

                return $sql;
            }

            return $sql;
        }

        if ([] !== $insertData) {
            $fields = [];
            $values = [];

            foreach ($insertData as $field => $value) {
                $validatedField = SqlSecurity::validateFieldName($field);
                $fields[] = $this->driver->quoteName($validatedField);

                if ($value instanceof QueryBuilder) {
                    $values[] = '('.$value->getQuery().')';
                } elseif (\is_array($value)) {
                    $formatted = array_map(fn ($item): string => $this->driver->quoteValue((string) $item), $value);
                    $values[] = '('.implode(', ', $formatted).')';
                } elseif (is_numeric($value)) {
                    $values[] = (string) $value;
                } else {
                    $values[] = $this->driver->quoteValue((string) $value);
                }
            }

            $sql .= ' ('.implode(', ', $fields).')';
            $sql .= "\nVALUES (".implode(', ', $values).')';

            $onConflict = $this->queryBuilder->getInsertConflictData();

            if ('' !== $onConflict && '0' !== $onConflict) {
                $sql .= "\n".$onConflict;
            }

            return $sql;
        }

        return $sql;
    }

    #[\Override]
    public function buildProcedure(): string
    {
        $procedureName = $this->queryBuilder->getProcedureName();

        if ('' === $procedureName || '0' === $procedureName) {
            return '';
        }

        $sql = 'CALL '.$this->driver->quoteName($procedureName);

        $params = $this->queryBuilder->getProcedureParams();

        $formattedParams = [];

        foreach ($params as $param) {
            if (null === $param) {
                $formattedParams[] = 'NULL';
            } elseif (\is_bool($param)) {
                $formattedParams[] = $param ? 'TRUE' : 'FALSE';
            } elseif (is_numeric($param)) {
                $formattedParams[] = (string) $param;
            } else {
                $formattedParams[] = $this->driver->quoteValue((string) $param);
            }
        }

        return $sql.('('.implode(', ', $formattedParams).')');
    }
}
