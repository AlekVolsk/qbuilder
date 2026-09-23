<?php

declare(strict_types=1);

namespace QBuilder\Condition;

use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlSecurity;

/**
 * Universal condition builder for SQL queries.
 *
 * Supports building conditions for WHERE, HAVING, JOIN and other SQL constructs.
 * Always works in conjunction with QueryBuilder to access database driver.
 *
 * @example
 * // For WHERE/HAVING
 * $qb->where()
 *    ->eq('status', 1)
 *    ->and()
 *    ->in('role', ['admin', 'user'])
 *    ->like('name', 'John', QbConsts::LIKE_RIGHT) // name LIKE 'John%'
 *    ->end();
 */
class ConditionBuilder extends Condition
{
    /**
     * Parent condition builder for nested groups.
     * Null for root-level conditions (WHERE, HAVING).
     */
    protected ?ConditionBuilder $parent = null;

    /**
     * IN subquery: field IN (SELECT ...).
     *
     * @param Field|string $field    Field name
     * @param QueryBuilder $subquery Subquery
     *
     * @throws InvalidQueryException If subquery is not a SELECT
     *
     * @example
     * $qb->where()->inSubquery('user_id', $subquery)->end()
     */
    public function inSubquery(Field|string $field, QueryBuilder $subquery): self
    {
        if ('SELECT' !== $subquery->getType()) {
            throw new InvalidQueryException('Subquery for IN must be a SELECT statement');
        }

        $fieldName = $this->formatFieldName($field);
        $subquerySql = $subquery->build();

        $this->addRawCondition($fieldName.' IN ('.$subquerySql.')');

        return $this;
    }

    /**
     * NOT IN subquery: field NOT IN (SELECT ...).
     *
     * @param Field|string $field    Field name
     * @param QueryBuilder $subquery Subquery
     *
     * @throws InvalidQueryException If subquery is not a SELECT
     *
     * @example
     * $qb->where()->notInSubquery('user_id', $subquery)->end()
     */
    public function notInSubquery(Field|string $field, QueryBuilder $subquery): self
    {
        if ('SELECT' !== $subquery->getType()) {
            throw new InvalidQueryException('Subquery for NOT IN must be a SELECT statement');
        }

        $fieldName = $this->formatFieldName($field);
        $subquerySql = $subquery->build();

        $this->addRawCondition($fieldName.' NOT IN ('.$subquerySql.')');

        return $this;
    }

    /**
     * EXISTS subquery: EXISTS (SELECT ...).
     *
     * @param QueryBuilder $subquery Subquery
     *
     * @throws InvalidQueryException If subquery is not a SELECT
     *
     * @example
     * $qb->where()->exists($subquery)->end()
     */
    public function exists(QueryBuilder $subquery): self
    {
        if ('SELECT' !== $subquery->getType()) {
            throw new InvalidQueryException('Subquery for EXISTS must be a SELECT statement');
        }

        $subquerySql = $subquery->build();
        $this->addRawCondition('EXISTS ('.$subquerySql.')');

        return $this;
    }

    /**
     * NOT EXISTS subquery: NOT EXISTS (SELECT ...).
     *
     * @param QueryBuilder $subquery Subquery
     *
     * @throws InvalidQueryException If subquery is not a SELECT
     *
     * @example
     * $qb->where()->notExists($subquery)->end()
     */
    public function notExists(QueryBuilder $subquery): self
    {
        if ('SELECT' !== $subquery->getType()) {
            throw new InvalidQueryException('Subquery for NOT EXISTS must be a SELECT statement');
        }

        $subquerySql = $subquery->build();
        $this->addRawCondition('NOT EXISTS ('.$subquerySql.')');

        return $this;
    }

    /**
     * Compare field with subquery result: field OPERATOR (SELECT ...).
     *
     * @param Field|string $field    Field name
     * @param string       $operator Comparison operator (=, >, <, >=, <=, !=, <>, LIKE, etc.)
     * @param QueryBuilder $subquery Subquery
     *
     * @throws InvalidQueryException If subquery is not a SELECT or operator is invalid
     *
     * @example
     * $qb->where()->compareSubquery('price', '>', $subquery)->end()
     */
    public function compareSubquery(
        Field|string $field,
        string $operator,
        QueryBuilder $subquery
    ): self {
        if ('SELECT' !== $subquery->getType()) {
            throw new InvalidQueryException('Subquery for comparison must be a SELECT statement');
        }

        $driver = $this->queryBuilder->getDriver();
        $validatedOperator = SqlSecurity::validateComparisonOperator($operator, $driver);

        $fieldName = $this->formatFieldName($field);
        $subquerySql = $subquery->build();

        $this->addRawCondition($fieldName.' '.$validatedOperator.' ('.$subquerySql.')');

        return $this;
    }

    /**
     * Set logical operator to AND for next condition.
     *
     * @example
     * $qb->where()->eq('status', 1)->and()->eq('active', 1)->end()
     */
    public function and(): self
    {
        $this->currentLogic = 'AND';

        return $this;
    }

    /**
     * Set logical operator to OR for next condition.
     *
     * @example
     * $qb->where()->eq('status', 1)->or()->eq('status', 2)->end()
     */
    public function or(): self
    {
        $this->currentLogic = 'OR';

        return $this;
    }

    /**
     * Start nested condition group with AND.
     *
     * @param null|callable(ConditionBuilder): ConditionBuilder $closure Optional closure for building group conditions
     *
     * @example
     * // Standard syntax
     * $qb->where()
     *    ->eq('status', 1)
     *    ->andGroup()
     *        ->eq('role', 'admin')
     *        ->or()
     *        ->eq('role', 'moderator')
     *    ->end()
     *    ->end();
     *
     * // Alternative syntax with closure
     * $qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
     *    $q->eq('status', 1)
     *      ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
     *          ->eq('role', 'admin')
     *          ->or()
     *          ->eq('role', 'moderator'))
     * );
     */
    public function andGroup(?callable $closure = null): self
    {
        $group = $this->startGroup('AND');

        if (null !== $closure) {
            $closure($group);
            $builtCondition = $group->build();

            if ('' !== $builtCondition && '0' !== $builtCondition) {
                $this->addRawCondition(trim($builtCondition));
            }

            return $this;
        }

        return $group;
    }

    /**
     * Start nested condition group with OR.
     *
     * @param null|callable(ConditionBuilder): ConditionBuilder $closure Optional closure for building group conditions
     *
     * @example
     * // Standard syntax
     * $qb->where()
     *    ->eq('status', 1)
     *    ->orGroup()
     *        ->eq('role', 'admin')
     *        ->eq('role', 'moderator')
     *    ->end()
     *    ->end();
     *
     * // Alternative syntax with closure
     * $qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
     *    $q->eq('status', 1)
     *      ->orGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
     *          ->eq('role', 'admin')
     *          ->or()
     *          ->eq('role', 'moderator'))
     * );
     */
    public function orGroup(?callable $closure = null): self
    {
        $group = $this->startGroup('OR');

        if (null !== $closure) {
            $closure($group);
            $builtCondition = $group->build();

            if ('' !== $builtCondition && '0' !== $builtCondition) {
                $this->addRawCondition(trim($builtCondition));
            }

            return $this;
        }

        return $group;
    }

    /**
     * End current condition builder and return to QueryBuilder.
     *
     * Note: This method supports nested groups (andGroup/orGroup) which can return
     * parent ConditionBuilder, but these are currently not used in the project.
     * In practice, this always returns QueryBuilder.
     */
    public function end(): QueryBuilder
    {
        if ($this->parent instanceof self) {
            $builtCondition = $this->build();

            if ('' !== $builtCondition && '0' !== $builtCondition) {
                $this->parent->addRawCondition(trim($builtCondition));
            }

            return $this->parent->end();
        }

        return $this->queryBuilder;
    }

    /**
     * Clear all conditions.
     * Used when reset() is called in QueryBuilder.
     */
    public function reset(): void
    {
        parent::reset();
        $this->parent = null;
    }

    /**
     * Start nested condition group.
     *
     * @param string $logic Logical operator: 'AND' or 'OR'
     */
    protected function startGroup(string $logic): self
    {
        $group = new self($this->queryBuilder, $this->clauseType);
        $group->parent = $this;

        if ([] !== $this->conditions) {
            $this->currentLogic = $logic;
        }

        return $group;
    }
}
