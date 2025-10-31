<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConditionAdvancedTest extends TestCase
{
    public function testNotBetween(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notBetween('age', 18, 65)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`age` NOT BETWEEN 18 AND 65)', $sql);
    }

    public function testNotBetweenWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->notBetween(Field::set('age', 'u'), 18, 65)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`age` NOT BETWEEN 18 AND 65)', $sql);
    }

    public function testRaw(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->raw('1 = 1')
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (1 = 1)', $sql);
    }

    public function testRawWithMultipleConditions(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->and()
            ->raw('EXISTS (SELECT 1 FROM orders WHERE orders.user_id = users.id)')
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('EXISTS', $sql);
        self::assertStringContainsString('orders', $sql);
    }

    public function testBetweenWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->between(Field::set('age', 'u'), 18, 65)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`age` BETWEEN 18 AND 65)', $sql);
    }

    public function testBetweenWithStringValues(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->between('created_at', '2020-01-01', '2024-12-31')
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('BETWEEN', $sql);
        self::assertStringContainsString('2020-01-01', $sql);
        self::assertStringContainsString('2024-12-31', $sql);
    }

    public function testNotBetweenWithStringValues(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notBetween('created_at', '2020-01-01', '2024-12-31')
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('NOT BETWEEN', $sql);
        self::assertStringContainsString('2020-01-01', $sql);
        self::assertStringContainsString('2024-12-31', $sql);
    }

    public function testInWithEmptyArrayReturnsFalseCondition(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('id', [])
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('1 = 0', $sql);
    }

    public function testNotInWithEmptyArrayReturnsTrueCondition(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notIn('id', [])
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('1 = 1', $sql);
    }

    public function testInWithCommaSeparatedString(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('status', 'active,pending,verified')
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('IN', $sql);
        self::assertStringContainsString('active', $sql);
        self::assertStringContainsString('pending', $sql);
        self::assertStringContainsString('verified', $sql);
    }

    public function testNotInWithCommaSeparatedString(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notIn('status', 'banned,deleted')
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('NOT IN', $sql);
        self::assertStringContainsString('banned', $sql);
        self::assertStringContainsString('deleted', $sql);
    }

    public function testLikeWithLeftBoundary(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->like('email', 'example.com', QbConsts::LIKE_LEFT)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('LIKE', $sql);
        self::assertStringContainsString('%example.com', $sql);
        self::assertStringNotContainsString('example.com%', $sql);
    }

    public function testLikeWithRightBoundary(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->like('name', 'John', QbConsts::LIKE_RIGHT)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('LIKE', $sql);
        self::assertStringContainsString('John%', $sql);
        self::assertStringNotContainsString('%John', $sql);
    }

    public function testNotLikeWithFullBoundary(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notLike('name', 'Admin', QbConsts::LIKE_FULL)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('NOT LIKE', $sql);
        self::assertStringContainsString('%Admin%', $sql);
    }

    public function testNotLikeWithLeftBoundary(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notLike('email', 'example.com', QbConsts::LIKE_LEFT)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('NOT LIKE', $sql);
        self::assertStringContainsString('%example.com', $sql);
    }

    public function testNotLikeWithRightBoundary(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notLike('name', 'John', QbConsts::LIKE_RIGHT)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('NOT LIKE', $sql);
        self::assertStringContainsString('John%', $sql);
    }

    public function testHasConditionsReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        self::assertFalse($where->hasConditions());

        $where->eq('status', 'active');

        self::assertTrue($where->hasConditions());
    }

    public function testResetClearsConditions(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();
        $where->eq('status', 'active')
            ->gt('age', 18)
        ;

        self::assertTrue($where->hasConditions());

        $where->reset();

        self::assertFalse($where->hasConditions());
        self::assertSame('', $where->build());
    }

    public function testBuildReturnsEmptyStringForEmptyConditions(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        self::assertSame('', $where->build());
    }

    public function testBetweenWithNumericValues(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('products')
            ->where()
            ->between('price', 10.5, 99.99)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('BETWEEN', $sql);
        self::assertStringContainsString('10.5', $sql);
        self::assertStringContainsString('99.99', $sql);
    }

    public function testNotBetweenWithNumericValues(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('products')
            ->where()
            ->notBetween('price', 10.5, 99.99)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('NOT BETWEEN', $sql);
        self::assertStringContainsString('10.5', $sql);
        self::assertStringContainsString('99.99', $sql);
    }

    public function testInFiltersEmptyStrings(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('status', ['active', '', 'pending', ''])
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('active', $sql);
        self::assertStringContainsString('pending', $sql);
        self::assertStringNotContainsString('\'\'', $sql);
    }

    public function testNotInFiltersEmptyStrings(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notIn('status', ['banned', '', 'deleted'])
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('banned', $sql);
        self::assertStringContainsString('deleted', $sql);
        self::assertStringNotContainsString('\'\'', $sql);
    }

    public function testIsNullWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->isNull(Field::set('deleted_at', 'u'))
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`deleted_at` IS NULL)', $sql);
    }

    public function testIsNotNullWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->isNotNull(Field::set('email', 'u'))
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`email` IS NOT NULL)', $sql);
    }

    public function testIsNullWithStringField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->isNull('deleted_at')
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`deleted_at` IS NULL)', $sql);
    }

    public function testIsNotNullWithStringField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->isNotNull('email')
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`email` IS NOT NULL)', $sql);
    }

    public function testIsNullAndIsNotNullCombined(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->isNull('deleted_at')
            ->and()
            ->isNotNull('email')
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('`deleted_at` IS NULL', $sql);
        self::assertStringContainsString('`email` IS NOT NULL', $sql);
    }

    public function testEqWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->eq(Field::set('status', 'u'), 'active')
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`status` = \'active\')', $sql);
    }

    public function testNeqWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->neq(Field::set('status', 'u'), 'inactive')
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`status` != \'inactive\')', $sql);
    }

    public function testGtWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('products', 'p')
            ->where()
            ->gt(Field::set('price', 'p'), 100)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` > 100)', $sql);
    }

    public function testGteWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('products', 'p')
            ->where()
            ->gte(Field::set('price', 'p'), 100)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` >= 100)', $sql);
    }

    public function testLtWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('products', 'p')
            ->where()
            ->lt(Field::set('price', 'p'), 1000)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` < 1000)', $sql);
    }

    public function testLteWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('products', 'p')
            ->where()
            ->lte(Field::set('price', 'p'), 1000)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` <= 1000)', $sql);
    }

    public function testLikeWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->like(Field::set('name', 'u'), 'John', QbConsts::LIKE_RIGHT)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('`u`.`name` LIKE', $sql);
        self::assertStringContainsString('John%', $sql);
    }

    public function testNotLikeWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()
            ->notLike(Field::set('email', 'u'), 'test', QbConsts::LIKE_FULL)
            ->end()
            ->build(true)
        ;

        self::assertStringContainsString('`u`.`email` NOT LIKE', $sql);
        self::assertStringContainsString('%test%', $sql);
    }
}
