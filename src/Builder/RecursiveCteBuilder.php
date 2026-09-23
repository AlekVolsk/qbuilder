<?php

declare(strict_types=1);

namespace QBuilder\Builder;

use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlCompactor;
use QBuilder\Services\SqlSecurity;

/**
 * Recursive CTE (Common Table Expressions) query builder.
 *
 * Used for working with tree-like data structures.
 * Supports building recursive queries of WITH RECURSIVE type.
 *
 * @example
 * // Build category tree with parent path
 * $sql = $qb->recursiveCte('CategoryTree')
 *     ->baseQuery(
 *         $qb->select('id', 'parent_id', 'name', '0 AS level', 'name AS path')
 *            ->from('categories')
 *            ->where()->isNull('parent_id')->end()
 *     )
 *     ->recursiveQuery(
 *         $qb->select('c.id', 'c.parent_id', 'c.name', 't.level + 1', "CONCAT(t.path, ' / ', c.name)")
 *            ->from('categories', 'c')
 *            ->innerJoin('CategoryTree', 't', ConditionJoin::create($qb, 'c.parent_id', 'id', 't'))
 *     )
 *     ->finalSelect(
 *         $qb->select('id', 'name', 'level', 'path')
 *            ->from('CategoryTree')
 *            ->orderBy(ConditionBy::orderBy()->asc('path'))
 *     )
 *     ->build();
 */
class RecursiveCteBuilder
{
    protected string $cteName = '';
    protected ?QueryBuilder $baseQuery = null;
    protected ?QueryBuilder $recursiveQuery = null;
    protected ?QueryBuilder $finalSelect = null;

    /**
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     * @param string       $cteName      CTE table name
     */
    public function __construct(protected QueryBuilder $queryBuilder, string $cteName = 'RecursiveCTE')
    {
        $this->cteName = SqlSecurity::validateTableName($cteName);
    }

    /**
     * Create new RecursiveCteBuilder instance.
     *
     * @param QueryBuilder $queryBuilder Parent QueryBuilder
     * @param string       $cteName      CTE table name (default 'RecursiveCTE')
     *
     * @example
     * $cte = RecursiveCteBuilder::create($qb, 'AllAncestors');
     */
    public static function create(QueryBuilder $queryBuilder, string $cteName = 'RecursiveCTE'): self
    {
        return new self($queryBuilder, $cteName);
    }

    /**
     * Set base query (anchor member) for CTE.
     * This is the initial point of recursion.
     *
     * @param QueryBuilder $query Base SELECT query
     *
     * @throws InvalidQueryException If non-SELECT query is passed
     *
     * @example
     * ->baseQuery(
     *     $qb->select('id', 'id AS descendant_id', 'parent_id', 'name', '0 AS level')
     *        ->from('categories')
     *        ->where()->eq('status', 1)->end()
     * )
     */
    public function baseQuery(QueryBuilder $query): self
    {
        if ('SELECT' !== $query->getType()) {
            throw new InvalidQueryException('Base query must be a SELECT statement');
        }

        $this->baseQuery = $query;

        return $this;
    }

    /**
     * Set recursive query (recursive member) for CTE.
     * This query executes recursively, referencing the CTE name.
     *
     * @param QueryBuilder $query Recursive SELECT query
     *
     * @throws InvalidQueryException If non-SELECT query is passed
     *
     * @example
     * ->recursiveQuery(
     *     $qb->select('c.id', 'a.descendant_id', 'c.parent_id', 'c.name', 'a.level + 1')
     *        ->from('categories', 'c')
     *        ->join('AllAncestors', 'a', ConditionJoin::create($db, 'c.id', 'a.parent_id'))
     * )
     */
    public function recursiveQuery(QueryBuilder $query): self
    {
        if ('SELECT' !== $query->getType()) {
            throw new InvalidQueryException('Recursive query must be a SELECT statement');
        }

        $this->recursiveQuery = $query;

        return $this;
    }

    /**
     * Set final SELECT query that uses the CTE.
     *
     * @param QueryBuilder $query Final SELECT query
     *
     * @throws InvalidQueryException If non-SELECT query is passed
     *
     * @example
     * ->finalSelect(
     *     $qb->select('descendant_id', 'GROUP_CONCAT(name ORDER BY level DESC SEPARATOR " / ") AS with_parent_name')
     *        ->from('AllAncestors')
     *        ->groupBy(ConditionBy::groupBy()->add('descendant_id'))
     * )
     */
    public function finalSelect(QueryBuilder $query): self
    {
        if ('SELECT' !== $query->getType()) {
            throw new InvalidQueryException('Final query must be a SELECT statement');
        }

        $this->finalSelect = $query;

        return $this;
    }

    /**
     * Build final SQL query with WITH RECURSIVE.
     *
     * @param bool $compact Remove extra newlines and spaces
     *
     * @return string Ready SQL query
     *
     * @throws MissingRequirementException If required query parts are not set
     */
    public function build(bool $compact = false): string
    {
        if (! $this->baseQuery instanceof QueryBuilder) {
            throw new MissingRequirementException('Base query is required for recursive CTE');
        }

        if (! $this->recursiveQuery instanceof QueryBuilder) {
            throw new MissingRequirementException('Recursive query is required for recursive CTE');
        }

        if (! $this->finalSelect instanceof QueryBuilder) {
            throw new MissingRequirementException('Final SELECT query is required for recursive CTE');
        }

        $driver = $this->getDriverInstance();
        $sql = 'WITH RECURSIVE '.$driver->quoteName($this->cteName)." AS (\n";

        $sql .= $this->indentQuery($this->baseQuery->build());

        $sql .= "\n    UNION ALL\n";

        $sql .= $this->indentQuery($this->recursiveQuery->build());

        $sql .= "\n)\n";

        $sql .= $this->finalSelect->build();

        if ($compact) {
            $compactor = new SqlCompactor();

            return $compactor->compact($sql);
        }

        return $sql;
    }

    /**
     * Get ready query.
     *
     * @param bool $compact Remove extra newlines and spaces (default false)
     *
     * @return string Ready SQL query
     *
     * @example
     * $sql = $cte->getQuery();
     * $compactSql = $cte->getQuery(true);
     */
    public function getQuery(bool $compact = false): string
    {
        return $this->build($compact);
    }

    /**
     * Get CTE name.
     */
    public function getCteName(): string
    {
        return $this->cteName;
    }

    /**
     * Get driver instance.
     */
    protected function getDriverInstance(): DriverInterface
    {
        return $this->queryBuilder->getDriverInstance();
    }

    /**
     * Add indentation to query for readability.
     *
     * @param string $query SQL query
     *
     * @return string SQL with indentation
     */
    protected function indentQuery(string $query): string
    {
        $lines = explode("\n", $query);
        $indentedLines = [];

        foreach ($lines as $line) {
            $indentedLines[] = '    '.$line;
        }

        return implode("\n", $indentedLines);
    }
}
