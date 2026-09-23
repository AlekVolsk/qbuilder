<?php

declare(strict_types=1);

namespace QBuilder\Condition;

use QBuilder\QueryBuilder;

/**
 * Class for building JOIN conditions.
 * Extends Condition to support all condition methods (bitmask, findInSet, etc).
 * Supports multiple conditions with AND/OR logical operators.
 */
class ConditionJoin extends Condition
{
    protected string $joinAlias = '';

    /**
     * Constructor for quick simple condition creation.
     *
     * @param QueryBuilder              $queryBuilder Parent QueryBuilder
     * @param null|string               $field        Join table field
     * @param null|array<string>|string $target       Target table field or array ['field', 'table']
     * @param null|string               $targetTable  Target table or alias (if $target is string)
     *
     * @example
     * new ConditionJoin($qb, 'user_id', 'id', 'users')
     * new ConditionJoin($qb, 'user_id', ['id', 'users'])
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        ?string $field = null,
        array|string|null $target = null,
        ?string $targetTable = null
    ) {
        parent::__construct($queryBuilder, 'JOIN');

        if (null !== $field && null !== $target) {
            $targetField = $this->prepareTarget($target, $targetTable);
            $fieldName = $this->formatFieldName($field);
            $targetName = $this->formatFieldName($targetField);
            $this->addRawCondition($fieldName.' = '.$targetName);
        }
    }

    /**
     * Create new ConditionJoin object.
     *
     * @param QueryBuilder              $queryBuilder Parent QueryBuilder
     * @param null|string               $field        Join table field
     * @param null|array<string>|string $target       Target table field or array ['field', 'table']
     * @param null|string               $targetTable  Target table or alias
     *
     * @example
     * // Simple condition
     * ConditionJoin::create($qb, 'user_id', 'id', 'users')
     *
     * // Multiple conditions
     * ConditionJoin::create($qb)
     *     ->eq('user_id', 'id', 'users')
     *     ->and()
     *     ->eq('status', 'active')
     */
    public static function create(
        QueryBuilder $queryBuilder,
        ?string $field = null,
        array|string|null $target = null,
        ?string $targetTable = null
    ): self {
        return new self($queryBuilder, $field, $target, $targetTable);
    }

    /**
     * Set join table alias (internal method).
     *
     * @param string $alias Table alias
     */
    public function setJoinAlias(string $alias): self
    {
        $this->joinAlias = $alias;

        return $this;
    }

    /**
     * Get join table alias.
     */
    public function getJoinAlias(): string
    {
        return $this->joinAlias;
    }

    /**
     * Set logical operator to AND for next condition.
     */
    public function and(): self
    {
        $this->currentLogic = 'AND';

        return $this;
    }

    /**
     * Set logical operator to OR for next condition.
     */
    public function or(): self
    {
        $this->currentLogic = 'OR';

        return $this;
    }

    /**
     * Prepare target field (convert array to Field object or string).
     *
     * @param array<string>|string $target      Target field or array ['field', 'table']
     * @param null|string          $targetTable Target table (if $target is string)
     */
    protected function prepareTarget(array|string $target, ?string $targetTable = null): Field|string
    {
        if (\is_array($target)) {
            [$fieldName, $tableName] = $target;

            return new Field($fieldName, $tableName);
        }

        if (null !== $targetTable) {
            return new Field($target, $targetTable);
        }

        return $target;
    }
}
