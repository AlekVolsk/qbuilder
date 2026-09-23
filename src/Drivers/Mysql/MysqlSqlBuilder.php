<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mysql;

use QBuilder\Condition\ConditionJoin;
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
    protected function buildInsertRows(string $sql, array $insertRows, array $insertFields): string
    {
        $sql = parent::buildInsertRows($sql, $insertRows, $insertFields);

        $conflictData = $this->queryBuilder->getInsertConflictData();

        if ('' !== $conflictData && '0' !== $conflictData) {
            $sql = str_replace("\n".$conflictData, "\nON DUPLICATE KEY UPDATE ".$conflictData, $sql);
        }

        return $sql;
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

    #[\Override]
    protected function buildJoinConditions(ConditionJoin $conditions, string $joinAlias = ''): string
    {
        $sql = parent::buildJoinConditions($conditions, $joinAlias);

        if ('' !== $joinAlias && '0' !== $joinAlias) {
            $sql = $this->addJoinAliasToFields($sql, $joinAlias);
        }

        return $sql;
    }

    /**
     * Add join table alias to fields without table in JOIN conditions.
     * MySQL-specific helper for automatic alias addition.
     *
     * @param string $sql       SQL condition
     * @param string $joinAlias Join table alias
     *
     * @return string SQL with added aliases
     */
    protected function addJoinAliasToFields(string $sql, string $joinAlias): string
    {
        $quote = $this->driver->getIdentifierQuote();

        return (string) preg_replace(
            '/\(('.preg_quote($quote, '/').'[^'.preg_quote($quote, '/').']+'
                .preg_quote($quote, '/').')\s*(=|!=|>|>=|<|<=)/',
            '('.$this->driver->quoteName($joinAlias).'.$1 $2',
            $sql
        );
    }
}
