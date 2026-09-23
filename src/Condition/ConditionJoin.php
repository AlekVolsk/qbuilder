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
     * Placeholder put before unqualified fields; build() replaces it with the join table alias.
     * Random per instance, so it cannot be forged by a value inside a string literal.
     */
    private readonly string $joinAliasMarker;

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
        $this->joinAliasMarker = "\0qb_join_alias_".bin2hex(random_bytes(8))."\0";

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
     * Build JOIN conditions; unqualified fields are qualified with the join table alias.
     *
     * @return string SQL conditions
     */
    #[\Override]
    public function build(): string
    {
        $prefix = '' === $this->joinAlias ? '' : $this->getDriver()->quoteName($this->joinAlias).'.';

        return str_replace($this->joinAliasMarker, $prefix, parent::build());
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

    /**
     * Format field name; an unqualified field refers to the join table and gets the alias marker.
     *
     * @param Field|string $field Field name
     *
     * @return string Formatted field name
     */
    #[\Override]
    protected function formatFieldName(Field|string $field): string
    {
        $formatted = parent::formatFieldName($field);

        $isUnqualified = $field instanceof Field
            ? '' === $field->tableOrAlias && ! $field->isExpression() && '*' !== $field->name
            : ! str_contains($field, '.') && '*' !== $field;

        return $isUnqualified ? $this->joinAliasMarker.$formatted : $formatted;
    }
}
