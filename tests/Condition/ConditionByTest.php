<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QbConsts;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class ConditionByTest
{
    public function testOrderByCreatesOrderByBuilder(): void
    {
        $builder = ConditionBy::orderBy();

        Assert::instanceOf($builder, ConditionBy::class);
        Assert::same($builder->getType(), QbConsts::TYPE_ORDER);
    }

    public function testGroupByCreatesGroupByBuilder(): void
    {
        $builder = ConditionBy::groupBy();

        Assert::instanceOf($builder, ConditionBy::class);
        Assert::same($builder->getType(), QbConsts::TYPE_GROUP);
    }

    public function testAddForOrderByWithDirection(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->add('name', '', QbConsts::ORDER_ASC);
        $builder->add('created_at', 'users', QbConsts::ORDER_DESC);

        $items = $builder->getItems();
        Assert::count($items, 2);
        Assert::array($items[0])->hasKeys('direction');
        $direction0 = $items[0]['direction'] ?? null;
        Assert::same($direction0, QbConsts::ORDER_ASC);
        Assert::array($items[1])->hasKeys('direction');
        $direction1 = $items[1]['direction'] ?? null;
        Assert::same($direction1, QbConsts::ORDER_DESC);
    }

    public function testAddForOrderByWithoutDirectionDefaultsToAsc(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->add('name');

        $items = $builder->getItems();
        Assert::count($items, 1);
        Assert::array($items[0])->hasKeys('direction');
        $direction = $items[0]['direction'] ?? null;
        Assert::same($direction, QbConsts::ORDER_ASC);
    }

    public function testAddForGroupByIgnoresDirection(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('status', '', QbConsts::ORDER_DESC);

        $items = $builder->getItems();
        Assert::count($items, 1);
        Assert::array($items[0])->doesNotHaveKeys('direction');
    }

    public function testAddForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('user_id');
        $builder->add('status', 'orders');

        $items = $builder->getItems();
        Assert::count($items, 2);
        Assert::instanceOf($items[0]['field'], Field::class);
        Assert::instanceOf($items[1]['field'], Field::class);
    }

    public function testAscForOrderBy(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->asc('name');
        $builder->asc('created_at', 'users');

        $items = $builder->getItems();
        Assert::count($items, 2);
        Assert::array($items[0])->hasKeys('direction');
        $direction0 = $items[0]['direction'] ?? null;
        Assert::same($direction0, QbConsts::ORDER_ASC);
        Assert::array($items[1])->hasKeys('direction');
        $direction1 = $items[1]['direction'] ?? null;
        Assert::same($direction1, QbConsts::ORDER_ASC);
    }

    public function testDescForOrderBy(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->desc('name');
        $builder->desc('created_at', 'users');

        $items = $builder->getItems();
        Assert::count($items, 2);
        Assert::array($items[0])->hasKeys('direction');
        $direction0 = $items[0]['direction'] ?? null;
        Assert::same($direction0, QbConsts::ORDER_DESC);
        Assert::array($items[1])->hasKeys('direction');
        $direction1 = $items[1]['direction'] ?? null;
        Assert::same($direction1, QbConsts::ORDER_DESC);
    }

    public function testAscThrowsExceptionForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Method asc() is only available for ORDER BY')
        ;

        $builder->asc('name');
    }

    public function testDescThrowsExceptionForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Method desc() is only available for ORDER BY')
        ;

        $builder->desc('name');
    }

    public function testGetItemsReturnsAllItems(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->asc('name')->desc('created_at');

        $items = $builder->getItems();
        Assert::count($items, 2);
        Assert::array($items[0])->hasKeys('field');
        Assert::array($items[0])->hasKeys('direction');
        Assert::array($items[1])->hasKeys('field');
        Assert::array($items[1])->hasKeys('direction');
    }

    public function testGetFieldsReturnsArrayOfFields(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('user_id')->add('status', 'orders');

        $fields = $builder->getFields();
        Assert::count($fields, 2);
        Assert::instanceOf($fields[0], Field::class);
        Assert::instanceOf($fields[1], Field::class);
    }

    public function testIsEmptyReturnsTrueForEmptyBuilder(): void
    {
        $builder = ConditionBy::orderBy();

        Assert::true($builder->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyBuilder(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->asc('name');

        Assert::false($builder->isEmpty());
    }

    public function testGetTypeReturnsOrderForOrderBy(): void
    {
        $builder = ConditionBy::orderBy();

        Assert::same($builder->getType(), QbConsts::TYPE_ORDER);
    }

    public function testGetTypeReturnsGroupForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();

        Assert::same($builder->getType(), QbConsts::TYPE_GROUP);
    }

    public function testChainingMethods(): void
    {
        $builder = ConditionBy::orderBy()
            ->asc('name')
            ->desc('created_at', 'users')
            ->asc('status', 'orders')
        ;

        Assert::count($builder->getItems(), 3);
        Assert::false($builder->isEmpty());
    }

    public function testAddWithTableAlias(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->add('id', 'users', QbConsts::ORDER_ASC);

        $items = $builder->getItems();
        Assert::count($items, 1);
        Assert::same($items[0]['field']->tableOrAlias, 'users');
    }

    public function testAddCreatesFieldWithCorrectName(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('status');

        $items = $builder->getItems();
        Assert::same($items[0]['field']->name, 'status');
    }

    public function testMultipleAddCallsForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('user_id')
            ->add('status', 'orders')
            ->add('category_id', 'products')
        ;

        $items = $builder->getItems();
        Assert::count($items, 3);
    }

    public function testAddWithInvalidDirectionThrowsException(): void
    {
        $builder = ConditionBy::orderBy();

        Expect::exception(InvalidIdentifierException::class);

        $builder->add('name', '', 'INVALID');
    }

    public function testMixedAscAndDescCalls(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->asc('name')
            ->desc('created_at')
            ->asc('status')
        ;

        $items = $builder->getItems();
        Assert::array($items[0])->hasKeys('direction');
        $direction0 = $items[0]['direction'] ?? null;
        Assert::same($direction0, QbConsts::ORDER_ASC);
        Assert::array($items[1])->hasKeys('direction');
        $direction1 = $items[1]['direction'] ?? null;
        Assert::same($direction1, QbConsts::ORDER_DESC);
        Assert::array($items[2])->hasKeys('direction');
        $direction2 = $items[2]['direction'] ?? null;
        Assert::same($direction2, QbConsts::ORDER_ASC);
    }
}
