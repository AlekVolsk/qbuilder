<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Oracle;

use QBuilder\Drivers\AbstractSqlBuilder;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * SQL query builder for Oracle Database.
 *
 * Responsible for forming final SQL from query settings
 * taking into account Oracle Database specifics.
 */
class OracleSqlBuilder extends AbstractSqlBuilder
{
    #[\Override]
    public function buildProcedure(): string
    {
        $procedureName = $this->queryBuilder->getProcedureName();

        if ('' === $procedureName || '0' === $procedureName) {
            return '';
        }

        $params = $this->queryBuilder->getProcedureParams();

        $formattedParams = [];

        foreach ($params as $param) {
            if (null === $param) {
                $formattedParams[] = 'NULL';
            } elseif (\is_bool($param)) {
                $formattedParams[] = $param ? '1' : '0';
            } elseif (is_numeric($param)) {
                $formattedParams[] = (string) $param;
            } else {
                $formattedParams[] = $this->driver->quoteValue((string) $param);
            }
        }

        $sql = 'BEGIN '.$this->driver->quoteName($procedureName);

        if ([] !== $formattedParams) {
            $sql .= '('.implode(', ', $formattedParams).')';
        }

        return $sql.'; END;';
    }

    #[\Override]
    protected function getAliasKeyword(): string
    {
        return ' ';
    }

    #[\Override]
    protected function buildInsertRows(string $sql, array $insertRows, array $insertFields): string
    {
        $validatedFields = array_map(
            fn ($f): string => $this->driver->quoteName(SqlSecurity::validateFieldName($f)),
            $insertFields
        );
        $sql .= ' ('.implode(', ', $validatedFields).')';

        if (1 === \count($insertRows)) {
            $sql .= "\nVALUES ";
            $row = $insertRows[0];
            $valuePlaceholders = [];

            foreach ($insertFields as $field) {
                $value = $row[$field] ?? null;
                $valuePlaceholders[] = $this->formatValue($value);
            }
            $sql .= '('.implode(', ', $valuePlaceholders).')';
        } else {
            $allSelects = [];

            foreach ($insertRows as $index => $row) {
                $valuePlaceholders = [];

                foreach ($insertFields as $field) {
                    $value = $row[$field] ?? null;
                    $valuePlaceholders[] = $this->formatValue($value);
                }

                $selectPrefix = 0 === $index ? 'SELECT ' : 'UNION ALL SELECT ';
                $allSelects[] = $selectPrefix.implode(', ', $valuePlaceholders).' FROM DUAL';
            }
            $sql .= "\n".implode("\n", $allSelects);
        }

        $mergeData = $this->queryBuilder->getInsertConflictData();

        if ('' !== $mergeData && '0' !== $mergeData) {
            return $mergeData;
        }

        return $sql;
    }

    #[\Override]
    protected function formatField(array $field): string
    {
        if (($field['subqueryObj'] ?? null) instanceof QueryBuilder) {
            $subquerySql = '('.$field['subqueryObj']->build().')';

            if (! empty($field['alias'])) {
                $subquerySql .= ' AS '.$this->driver->quoteName($field['alias']);
            }

            return $subquerySql;
        }

        if ($field['isExpression']) {
            $fieldStr = SqlSecurity::escapeIdentifiersInExpression(
                $field['field'],
                $this->driver->getIdentifierQuote()
            );
        } else {
            if (! empty($field['table'])) {
                $fieldStr = $this->driver->quoteName($field['table'].'.'.$field['field']);
            } else {
                $fieldStr = $this->driver->quoteName($field['field']);
            }
        }

        if (! empty($field['alias'])) {
            $fieldStr .= ' '.$this->driver->quoteName($field['alias']);
        }

        return $fieldStr;
    }
}
