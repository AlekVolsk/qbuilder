<?php

declare(strict_types=1);

namespace QBuilder\Drivers;

use QBuilder\Condition\ConditionBuilder;
use QBuilder\Condition\ConditionJoin;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlCompactor;
use QBuilder\Services\SqlSecurity;

/**
 * Abstract SQL builder with common logic for all database drivers.
 *
 * Provides base implementation for building SQL queries from QueryBuilder state.
 * Specific drivers can override methods to implement database-specific syntax.
 */
abstract class AbstractSqlBuilder implements SqlBuilderInterface
{
    protected string $builtQuery = '';
    protected SqlCompactor $compactor;

    public function __construct(
        protected QueryBuilder $queryBuilder,
        protected DriverInterface $driver
    ) {
        $this->compactor = new SqlCompactor($driver->usesBackslashEscapes());
    }

    public function build(bool $compact = false): string
    {
        $sql = match ($this->queryBuilder->getType()) {
            'SELECT' => $this->buildSelect(),
            'INSERT' => $this->buildInsert(),
            'UPDATE' => $this->buildUpdate(),
            'DELETE' => $this->buildDelete(),
            'PROCEDURE' => $this->buildProcedure(),
            default => '',
        };

        if ($compact && '' !== $sql) {
            $sql = $this->compactor->compact($sql);
        }

        $this->builtQuery = $sql;

        return $sql;
    }

    public function buildSelect(): string
    {
        $sql = 'SELECT ';

        if ($this->queryBuilder->getDistinctEnabled()) {
            $sql .= 'DISTINCT ';
        }

        $sql .= $this->buildSelectFields();
        $sql .= $this->buildFromClause();
        $sql .= $this->buildJoinClauses();
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildHavingClause();
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitClause();

        return $sql;
    }

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
            return $this->buildInsertFromSubquery($sql, $insertData);
        }

        if ([] !== $insertRows) {
            return $this->buildInsertRows($sql, $insertRows, $insertFields);
        }

        return $sql;
    }

    public function buildUpdate(): string
    {
        $fromTable = $this->queryBuilder->getFromTable();
        $sql = 'UPDATE '.$this->driver->quoteName($fromTable);

        $updateData = $this->queryBuilder->getUpdateData();

        if ([] === $updateData) {
            return $sql;
        }

        $sql .= "\nSET ".$this->buildUpdateSets($updateData);

        $insertData = $this->queryBuilder->getInsertData();

        if (
            [] !== $insertData
            && isset($insertData['_update_subquery'])
            && $insertData['_update_subquery'] instanceof QueryBuilder
        ) {
            return $this->buildUpdateWithSubquery($sql, $insertData);
        }

        $sql .= $this->buildWhereClause();

        return $sql;
    }

    public function buildDelete(): string
    {
        $fromTable = $this->queryBuilder->getFromTable();
        $sql = 'DELETE FROM '.$this->driver->quoteName($fromTable);

        $insertData = $this->queryBuilder->getInsertData();

        if (
            [] !== $insertData
            && isset($insertData['_delete_subquery'])
            && $insertData['_delete_subquery'] instanceof QueryBuilder
        ) {
            return $this->buildDeleteWithSubquery($sql, $insertData);
        }

        $sql .= $this->buildWhereClause();

        return $sql;
    }

    abstract public function buildProcedure(): string;

    public function getBuiltQuery(): string
    {
        return $this->builtQuery;
    }

    /**
     * Build SELECT fields clause.
     */
    protected function buildSelectFields(): string
    {
        $selectFields = $this->queryBuilder->getSelectFields();

        if ([] === $selectFields) {
            return '*';
        }

        $fields = [];

        foreach ($selectFields as $field) {
            $fields[] = $this->formatField($field);
        }

        return implode(', ', $fields);
    }

    /**
     * Build FROM clause.
     */
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
        }

        $fromAlias = $this->queryBuilder->getFromAlias();

        if ('' !== $fromAlias && '0' !== $fromAlias) {
            $sql .= $this->getAliasKeyword().$this->driver->quoteName($fromAlias);
        }

        return $sql.$this->driver->buildIndexHints($this->queryBuilder->getFromIndexHints());
    }

    /**
     * Get alias keyword (AS or space).
     * Oracle doesn't use AS for table aliases.
     */
    protected function getAliasKeyword(): string
    {
        return ' AS ';
    }

    /**
     * Build JOIN clauses.
     */
    protected function buildJoinClauses(): string
    {
        $sql = '';

        foreach ($this->queryBuilder->getJoinClauses() as $position => $join) {
            $sql .= "\n".$join['type'].' JOIN '.$this->driver->quoteName($join['table']);

            if (! empty($join['alias']) && $join['alias'] !== $join['table']) {
                $sql .= $this->getAliasKeyword().$this->driver->quoteName($join['alias']);
            }

            $sql .= $this->driver->buildIndexHints($this->queryBuilder->getJoinIndexHints($position));

            if ('CROSS' !== $join['type'] && $join['conditions']->hasConditions()) {
                $tableOrAlias = ! empty($join['alias']) ? $join['alias'] : $join['table'];
                $join['conditions']->setJoinAlias($tableOrAlias);
                $sql .= ' ON'.$this->buildJoinConditions($join['conditions']);
            }
        }

        foreach ($this->queryBuilder->getJoinFromSelectClauses() as $join) {
            $sql .= "\n".$join['type'].' JOIN ('.$join['subQuery']->getQuery().')';

            if (! empty($join['alias'])) {
                $sql .= $this->getAliasKeyword().$this->driver->quoteName($join['alias']);
            }

            if ('CROSS' !== $join['type'] && $join['conditions']->hasConditions()) {
                $join['conditions']->setJoinAlias($join['alias']);
                $sql .= ' ON'.$this->buildJoinConditions($join['conditions']);
            }
        }

        return $sql;
    }

    /**
     * Build WHERE clause.
     */
    protected function buildWhereClause(): string
    {
        $whereBuilder = $this->queryBuilder->getWhereBuilder();

        if ($whereBuilder instanceof ConditionBuilder && $whereBuilder->hasConditions()) {
            return "\nWHERE".$whereBuilder->build();
        }

        return '';
    }

    /**
     * Build GROUP BY clause.
     */
    protected function buildGroupByClause(): string
    {
        $groupBy = $this->queryBuilder->getGroupBy();

        if ([] === $groupBy) {
            return '';
        }

        $groups = [];

        foreach ($groupBy as $groupField) {
            if (! empty($groupField['table'])) {
                $groups[] = $this->driver->quoteName($groupField['table'].'.'.$groupField['field']);
            } else {
                $groups[] = $this->driver->quoteName($groupField['field']);
            }
        }

        return "\nGROUP BY ".implode(', ', $groups);
    }

    /**
     * Build HAVING clause.
     */
    protected function buildHavingClause(): string
    {
        $havingBuilder = $this->queryBuilder->getHavingBuilder();

        if ($havingBuilder instanceof ConditionBuilder && $havingBuilder->hasConditions()) {
            return "\nHAVING".$havingBuilder->build();
        }

        return '';
    }

    /**
     * Build ORDER BY clause.
     */
    protected function buildOrderByClause(): string
    {
        $orderBy = $this->queryBuilder->getOrderBy();

        if ([] === $orderBy) {
            return '';
        }

        $orders = [];

        foreach ($orderBy as $order) {
            if (! empty($order['table'])) {
                $fieldStr = $this->driver->quoteName($order['table'].'.'.$order['field']);
            } else {
                $fieldStr = $this->driver->quoteName($order['field']);
            }
            $orders[] = $fieldStr.' '.$order['direction'];
        }

        return "\nORDER BY ".implode(', ', $orders);
    }

    /**
     * Build LIMIT clause.
     */
    protected function buildLimitClause(): string
    {
        $limitValue = $this->queryBuilder->getLimitValue();

        if (null !== $limitValue) {
            return $this->driver->getLimitSql(
                $limitValue,
                $this->queryBuilder->getOffsetValue(),
                $this->queryBuilder->isLimitWithTies()
            );
        }

        return '';
    }

    /**
     * Format field array for SELECT.
     *
     * @param array{
     *     field:string,
     *     table:string,
     *     alias:string,
     *     isExpression:?bool,
     *     isSubquery:?bool,
     *     subqueryObj:?QueryBuilder
     * } $field Field data
     *
     * @return string Formatted field
     */
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
            $fieldStr .= ' AS '.$this->driver->quoteName($field['alias']);
        }

        return $fieldStr;
    }

    /**
     * Build JOIN conditions.
     *
     * @param ConditionJoin $conditions JOIN conditions
     *
     * @return string SQL for JOIN conditions
     */
    protected function buildJoinConditions(ConditionJoin $conditions): string
    {
        return $conditions->build();
    }

    /**
     * Build INSERT from subquery.
     *
     * @param string                                                $sql        Base INSERT SQL
     * @param array<string, array<int, string>|QueryBuilder|string> $insertData Insert data array
     *
     * @return string Complete INSERT SQL
     */
    protected function buildInsertFromSubquery(string $sql, array $insertData): string
    {
        $subQuery = $insertData['_subquery'];
        $fields = $insertData['_fields'] ?? [];

        if (! empty($fields) && \is_array($fields)) {
            $validatedFields = array_map(
                fn (string $f): string => $this->driver->quoteName(SqlSecurity::validateFieldName($f)),
                $fields
            );
            $sql .= ' ('.implode(', ', $validatedFields).')';
        }

        if (! $subQuery instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Subquery must be an instance of QueryBuilder');
        }

        return $sql."\n".$subQuery->build();
    }

    /**
     * Build INSERT with multiple rows.
     *
     * @param string                             $sql          Base INSERT SQL
     * @param array<int, array<string, ?scalar>> $insertRows   Rows to insert
     * @param array<int, string>                 $insertFields Field names
     *
     * @return string Complete INSERT SQL
     */
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

        $conflictData = $this->queryBuilder->getInsertConflictData();

        if ('' !== $conflictData && '0' !== $conflictData) {
            $sql .= $this->buildInsertConflictSuffix($conflictData);
        }

        return $sql;
    }

    /**
     * Build UPDATE SET clause.
     *
     * @param array<string, ?scalar> $updateData Update data
     *
     * @return string SET clause
     */
    protected function buildUpdateSets(array $updateData): string
    {
        $sets = [];

        foreach ($updateData as $field => $value) {
            $validatedField = SqlSecurity::validateFieldName($field);
            $quotedField = $this->driver->quoteName($validatedField);
            $sets[] = $quotedField.' = '.$this->driver->formatValue($value);
        }

        return implode(', ', $sets);
    }

    /**
     * Build UPDATE with subquery.
     *
     * @param string                                                $sql        Base UPDATE SQL
     * @param array<string, array<int, string>|QueryBuilder|string> $insertData Insert data array
     *
     * @return string Complete UPDATE SQL
     */
    protected function buildUpdateWithSubquery(string $sql, array $insertData): string
    {
        $subQuery = $insertData['_update_subquery'];

        if (! $subQuery instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Subquery must be an instance of QueryBuilder');
        }

        $idField = $insertData['_update_id_field'] ?? 'id';
        $idField = \is_string($idField) ? $idField : 'id';

        return $sql."\nWHERE ".$this->driver->quoteName($idField)
            ." IN (\n".$subQuery->build()."\n)";
    }

    /**
     * Build DELETE with subquery.
     *
     * @param string                                                $sql        Base DELETE SQL
     * @param array<string, array<int, string>|QueryBuilder|string> $insertData Insert data array
     *
     * @return string Complete DELETE SQL
     */
    protected function buildDeleteWithSubquery(string $sql, array $insertData): string
    {
        $subQuery = $insertData['_delete_subquery'];

        if (! $subQuery instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Subquery must be an instance of QueryBuilder');
        }

        $idField = $insertData['_delete_id_field'] ?? 'id';
        $idField = \is_string($idField) ? $idField : 'id';

        return $sql."\nWHERE ".$this->driver->quoteName($idField)
            ." IN (\n".$subQuery->build()."\n)";
    }

    /**
     * Build conflict handling clause appended after INSERT ... VALUES rows.
     *
     * @param string $conflictData Clause built by the conflict builder
     *
     * @return string SQL fragment
     */
    protected function buildInsertConflictSuffix(string $conflictData): string
    {
        return "\n".$conflictData;
    }
}
