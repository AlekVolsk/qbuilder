<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QbConsts;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConditionByTest extends TestCase
{
    public function testOrderByCreatesOrderByBuilder(): void
    {
        $builder = ConditionBy::orderBy();

        self::assertInstanceOf(ConditionBy::class, $builder);
        self::assertSame(QbConsts::TYPE_ORDER, $builder->getType());
    }

    public function testGroupByCreatesGroupByBuilder(): void
    {
        $builder = ConditionBy::groupBy();

        self::assertInstanceOf(ConditionBy::class, $builder);
        self::assertSame(QbConsts::TYPE_GROUP, $builder->getType());
    }

    public function testAddForOrderByWithDirection(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->add('name', '', QbConsts::ORDER_ASC);
        $builder->add('created_at', 'users', QbConsts::ORDER_DESC);

        $items = $builder->getItems();
        self::assertCount(2, $items);
        self::assertArrayHasKey('direction', $items[0]);
        $direction0 = $items[0]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_ASC, $direction0);
        self::assertArrayHasKey('direction', $items[1]);
        $direction1 = $items[1]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_DESC, $direction1);
    }

    public function testAddForOrderByWithoutDirectionDefaultsToAsc(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->add('name');

        $items = $builder->getItems();
        self::assertCount(1, $items);
        self::assertArrayHasKey('direction', $items[0]);
        $direction = $items[0]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_ASC, $direction);
    }

    public function testAddForGroupByIgnoresDirection(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('status', '', QbConsts::ORDER_DESC);

        $items = $builder->getItems();
        self::assertCount(1, $items);
        self::assertArrayNotHasKey('direction', $items[0]);
    }

    public function testAddForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('user_id');
        $builder->add('status', 'orders');

        $items = $builder->getItems();
        self::assertCount(2, $items);
        self::assertInstanceOf(Field::class, $items[0]['field']);
        self::assertInstanceOf(Field::class, $items[1]['field']);
    }

    public function testAscForOrderBy(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->asc('name');
        $builder->asc('created_at', 'users');

        $items = $builder->getItems();
        self::assertCount(2, $items);
        self::assertArrayHasKey('direction', $items[0]);
        $direction0 = $items[0]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_ASC, $direction0);
        self::assertArrayHasKey('direction', $items[1]);
        $direction1 = $items[1]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_ASC, $direction1);
    }

    public function testDescForOrderBy(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->desc('name');
        $builder->desc('created_at', 'users');

        $items = $builder->getItems();
        self::assertCount(2, $items);
        self::assertArrayHasKey('direction', $items[0]);
        $direction0 = $items[0]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_DESC, $direction0);
        self::assertArrayHasKey('direction', $items[1]);
        $direction1 = $items[1]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_DESC, $direction1);
    }

    public function testAscThrowsExceptionForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Method asc() is only available for ORDER BY');

        $builder->asc('name');
    }

    public function testDescThrowsExceptionForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Method desc() is only available for ORDER BY');

        $builder->desc('name');
    }

    public function testGetItemsReturnsAllItems(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->asc('name')->desc('created_at');

        $items = $builder->getItems();
        self::assertCount(2, $items);
        self::assertArrayHasKey('field', $items[0]);
        self::assertArrayHasKey('direction', $items[0]);
        self::assertArrayHasKey('field', $items[1]);
        self::assertArrayHasKey('direction', $items[1]);
    }

    public function testGetFieldsReturnsArrayOfFields(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('user_id')->add('status', 'orders');

        $fields = $builder->getFields();
        self::assertCount(2, $fields);
        self::assertInstanceOf(Field::class, $fields[0]);
        self::assertInstanceOf(Field::class, $fields[1]);
    }

    public function testIsEmptyReturnsTrueForEmptyBuilder(): void
    {
        $builder = ConditionBy::orderBy();

        self::assertTrue($builder->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyBuilder(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->asc('name');

        self::assertFalse($builder->isEmpty());
    }

    public function testGetTypeReturnsOrderForOrderBy(): void
    {
        $builder = ConditionBy::orderBy();

        self::assertSame(QbConsts::TYPE_ORDER, $builder->getType());
    }

    public function testGetTypeReturnsGroupForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();

        self::assertSame(QbConsts::TYPE_GROUP, $builder->getType());
    }

    public function testChainingMethods(): void
    {
        $builder = ConditionBy::orderBy()
            ->asc('name')
            ->desc('created_at', 'users')
            ->asc('status', 'orders')
        ;

        self::assertCount(3, $builder->getItems());
        self::assertFalse($builder->isEmpty());
    }

    public function testAddWithTableAlias(): void
    {
        $builder = ConditionBy::orderBy();
        $builder->add('id', 'users', QbConsts::ORDER_ASC);

        $items = $builder->getItems();
        self::assertCount(1, $items);
        self::assertSame('users', $items[0]['field']->tableOrAlias);
    }

    public function testAddCreatesFieldWithCorrectName(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('status');

        $items = $builder->getItems();
        self::assertSame('status', $items[0]['field']->name);
    }

    public function testMultipleAddCallsForGroupBy(): void
    {
        $builder = ConditionBy::groupBy();
        $builder->add('user_id')
            ->add('status', 'orders')
            ->add('category_id', 'products')
        ;

        $items = $builder->getItems();
        self::assertCount(3, $items);
    }

    public function testAddWithInvalidDirectionThrowsException(): void
    {
        $builder = ConditionBy::orderBy();

        $this->expectException(InvalidIdentifierException::class);

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
        self::assertArrayHasKey('direction', $items[0]);
        $direction0 = $items[0]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_ASC, $direction0);
        self::assertArrayHasKey('direction', $items[1]);
        $direction1 = $items[1]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_DESC, $direction1);
        self::assertArrayHasKey('direction', $items[2]);
        $direction2 = $items[2]['direction'] ?? null;
        self::assertSame(QbConsts::ORDER_ASC, $direction2);
    }
}
