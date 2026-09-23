<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Mssql;

use QBuilder\Drivers\AbstractSqlBuilder;

/**
 * SQL query builder for MS SQL Server.
 *
 * Responsible for forming final SQL from query settings
 * taking into account MS SQL Server specifics.
 */
class MssqlSqlBuilder extends AbstractSqlBuilder
{
    #[\Override]
    public function buildSelect(): string
    {
        $sql = 'SELECT ';

        if ($this->queryBuilder->getDistinctEnabled()) {
            $sql .= 'DISTINCT ';
        }

        $limitValue = $this->queryBuilder->getLimitValue();
        $offsetValue = $this->queryBuilder->getOffsetValue();
        $withTies = $this->queryBuilder->isLimitWithTies();

        if (null !== $limitValue && (null === $offsetValue || 0 === $offsetValue)) {
            $sql .= 'TOP '.$limitValue;

            if ($withTies) {
                $sql .= ' WITH TIES';
            }

            $sql .= ' ';
        }

        $sql .= $this->buildSelectFields();
        $sql .= $this->buildFromClause();
        $sql .= $this->buildJoinClauses();
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildHavingClause();

        $orderBy = $this->buildOrderByClause();

        if ('' !== $orderBy) {
            $sql .= $orderBy;
        } elseif (null !== $limitValue && null !== $offsetValue && $offsetValue > 0) {
            $sql .= "\nORDER BY (SELECT NULL)";
        }

        if (null !== $limitValue && null !== $offsetValue && $offsetValue > 0) {
            $sql .= $this->driver->getLimitSql($limitValue, $offsetValue);
        }

        return $sql;
    }

    #[\Override]
    public function buildInsert(): string
    {
        $insertRows = $this->queryBuilder->getInsertRows();

        if ([] !== $insertRows) {
            $mergeData = $this->queryBuilder->getInsertConflictData();

            if ('' !== $mergeData && '0' !== $mergeData) {
                return $mergeData;
            }
        }

        return parent::buildInsert();
    }

    #[\Override]
    public function buildProcedure(): string
    {
        $procedureName = $this->queryBuilder->getProcedureName();

        if ('' === $procedureName || '0' === $procedureName) {
            return '';
        }

        $sql = 'EXEC '.$this->driver->quoteName($procedureName);

        $params = $this->queryBuilder->getProcedureParams();

        $formattedParams = [];

        foreach ($params as $param) {
            $formattedParams[] = $this->driver->formatValue($param);
        }

        if ([] !== $formattedParams) {
            $sql .= ' '.implode(', ', $formattedParams);
        }

        return $sql;
    }
}
