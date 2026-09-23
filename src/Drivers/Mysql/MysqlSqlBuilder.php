<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mysql;

use QBuilder\Drivers\AbstractSqlBuilder;
use QBuilder\QueryBuilder;

/**
 * SQL query builder for MySQL.
 *
 * Responsible for forming final SQL from query settings
 * taking into account MySQL/MariaDB specifics.
 */
class MysqlSqlBuilder extends AbstractSqlBuilder
{
    #[\Override]
    public function buildUpdate(): string
    {
        $sql = parent::buildUpdate();

        $limitValue = $this->queryBuilder->getLimitValue();

        if (null !== $limitValue) {
            $sql .= $this->driver->getLimitSql($limitValue, null);
        }

        return $sql;
    }

    #[\Override]
    public function buildDelete(): string
    {
        $sql = parent::buildDelete();

        $limitValue = $this->queryBuilder->getLimitValue();

        if (null !== $limitValue) {
            $sql .= $this->driver->getLimitSql($limitValue, null);
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
            $formattedParams[] = $this->driver->formatValue($param);
        }

        return $sql.('('.implode(', ', $formattedParams).')');
    }

    #[\Override]
    protected function buildGroupByClause(): string
    {
        $groupBy = $this->queryBuilder->getGroupBy();

        if ([] === $groupBy) {
            return '';
        }

        $groups = [];

        foreach ($groupBy as $field) {
            $groups[] = $this->formatField($field);
        }

        return "\nGROUP BY ".implode(', ', $groups);
    }

    #[\Override]
    protected function buildInsertConflictSuffix(string $conflictData): string
    {
        $rowAlias = $this->driver instanceof MysqlDriver && $this->driver->supportsInsertRowAlias()
            ? ' AS '.$this->driver->quoteName(MysqlDriver::INSERT_ROW_ALIAS)
            : '';

        return $rowAlias."\nON DUPLICATE KEY UPDATE ".$conflictData;
    }

    #[\Override]
    protected function formatField(array $field): string
    {
        if (($field['subqueryObj'] ?? null) instanceof QueryBuilder || $field['isExpression']) {
            return parent::formatField($field);
        }

        $fieldStr = '';

        if (! empty($field['table'])) {
            $fromTable = $this->queryBuilder->getFromTable();
            $fromAlias = $this->queryBuilder->getFromAlias();

            if ($field['table'] === $fromTable && ('' !== $fromAlias && '0' !== $fromAlias)) {
                $fieldStr = $this->driver->quoteName($fromAlias);
            } else {
                $fieldStr = $this->driver->quoteName($field['table']);
            }
            $fieldStr .= '.';
        } elseif (! \in_array($this->queryBuilder->getFromAlias(), ['', '0'], true)) {
            $fieldStr = $this->driver->quoteName($this->queryBuilder->getFromAlias()).'.';
        }

        if ('*' === $field['field']) {
            $fieldStr .= '*';
        } else {
            $fieldStr .= $this->driver->quoteName($field['field']);
        }

        if (! empty($field['alias'])) {
            $fieldStr .= ' AS '.$this->driver->quoteName($field['alias']);
        }

        return $fieldStr;
    }
}
