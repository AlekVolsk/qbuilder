<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConditionFieldComparisonTest extends TestCase
{
    public function testCompareFieldsWithFieldObjects(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        $reflection = new \ReflectionClass($where);
        $method = $reflection->getMethod('compareFields');
        $method->setAccessible(true);

        $field1 = Field::set('price', 'products');
        $field2 = Field::set('cost', 'products');

        $method->invoke($where, '', $field1, '=', '', $field2);
        $sql = $where->build();

        self::assertStringContainsString('`products`.`price` = `products`.`cost`', $sql);
    }

    public function testCompareFieldsWithStringFieldsAndTableNames(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        $reflection = new \ReflectionClass($where);
        $method = $reflection->getMethod('compareFields');
        $method->setAccessible(true);

        $method->invoke($where, 'users', 'id', '=', 'orders', 'user_id');
        $sql = $where->build();

        self::assertStringContainsString('`users`.`id` = `orders`.`user_id`', $sql);
    }

    public function testCompareFieldsWithMixedFieldAndString(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        $reflection = new \ReflectionClass($where);
        $method = $reflection->getMethod('compareFields');
        $method->setAccessible(true);

        $field1 = Field::set('price', 'products');
        $method->invoke($where, '', $field1, '>', 'products', 'cost');
        $sql = $where->build();

        self::assertStringContainsString('`products`.`price` > `products`.`cost`', $sql);
    }

    public function testCompareFieldsWithStringFieldAndFieldObject(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        $reflection = new \ReflectionClass($where);
        $method = $reflection->getMethod('compareFields');
        $method->setAccessible(true);

        $field2 = Field::set('cost', 'products');
        $method->invoke($where, 'products', 'price', '<', '', $field2);
        $sql = $where->build();

        self::assertStringContainsString('`products`.`price` < `products`.`cost`', $sql);
    }

    public function testCompareFieldsWithEmptyTableNames(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        $reflection = new \ReflectionClass($where);
        $method = $reflection->getMethod('compareFields');
        $method->setAccessible(true);

        $method->invoke($where, '', 'price', '=', '', 'cost');
        $sql = $where->build();

        self::assertStringContainsString('`price` = `cost`', $sql);
    }

    public function testCompareFieldsWithDifferentOperators(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        $reflection = new \ReflectionClass($where);
        $method = $reflection->getMethod('compareFields');
        $method->setAccessible(true);

        $method->invoke($where, '', 'a', '!=', '', 'b');
        $sql = $where->build();

        self::assertStringContainsString('`a` != `b`', $sql);
    }
}
