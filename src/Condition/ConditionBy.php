<?php

declare(strict_types=1);

namespace QBuilder\Condition;

use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QbConsts;
use QBuilder\Services\SqlSecurity;

/**
 * Universal builder for ORDER BY and GROUP BY expressions.
 *
 * Allows creating field lists with directions (for ORDER BY) or without (for GROUP BY).
 *
 * @example
 * // ORDER BY
 * ConditionBy::orderBy()
 *     ->add('name', '', QbConsts::ORDER_ASC)
 *     ->add('created_at', 'users', QbConsts::ORDER_DESC)
 *
 * // GROUP BY
 * ConditionBy::groupBy()
 *     ->add('user_id')
 *     ->add('status', 'orders')
 *
 * @api
 */
class ConditionBy
{
    /** @var array<array{field: Field, direction?: string}> */
    protected array $items = [];

    /**
     * @param string $type Type: ORDER or GROUP
     */
    protected function __construct(protected string $type) {}

    /**
     * Create builder for ORDER BY.
     *
     * @example
     * ConditionBy::orderBy()->asc('name')->desc('created_at', 'users')
     */
    public static function orderBy(): self
    {
        return new self(QbConsts::TYPE_ORDER);
    }

    /**
     * Create builder for GROUP BY.
     *
     * @example
     * ConditionBy::groupBy()->add('user_id')->add('status', 'orders')
     */
    public static function groupBy(): self
    {
        return new self(QbConsts::TYPE_GROUP);
    }

    /**
     * @param string      $field        Field name
     * @param string      $tableOrAlias Table or alias
     * @param null|string $direction    Sort direction (only for ORDER BY)
     */
    public function add(
        string $field,
        string $tableOrAlias = '',
        ?string $direction = null
    ): self {
        $item = ['field' => new Field($field, $tableOrAlias)];

        if (QbConsts::TYPE_ORDER === $this->type) {
            $directionValue = null !== $direction ? strtoupper($direction) : QbConsts::ORDER_ASC;
            $item['direction'] = SqlSecurity::validateOrderDirection($directionValue);
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * Add field with ascending sort (only for ORDER BY).
     *
     * @param string $field        Field name
     * @param string $tableOrAlias Table or alias
     */
    public function asc(string $field, string $tableOrAlias = ''): self
    {
        if (QbConsts::TYPE_ORDER !== $this->type) {
            throw new InvalidQueryException('Method asc() is only available for ORDER BY');
        }

        return $this->add($field, $tableOrAlias, QbConsts::ORDER_ASC);
    }

    /**
     * Add field with descending sort (only for ORDER BY).
     *
     * @param string $field        Field name
     * @param string $tableOrAlias Table or alias
     */
    public function desc(string $field, string $tableOrAlias = ''): self
    {
        if (QbConsts::TYPE_ORDER !== $this->type) {
            throw new InvalidQueryException('Method desc() is only available for ORDER BY');
        }

        return $this->add($field, $tableOrAlias, QbConsts::ORDER_DESC);
    }

    /**
     * Get all items.
     *
     * @return array<array{field: Field, direction?: string}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get fields (for backward compatibility with GroupBy).
     *
     * @return array<int,Field>
     */
    public function getFields(): array
    {
        return array_values(array_map(static fn (array $item): Field => $item['field'], $this->items));
    }

    /**
     * Check if list is empty.
     */
    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * Get type (ORDER or GROUP).
     */
    public function getType(): string
    {
        return $this->type;
    }
}
