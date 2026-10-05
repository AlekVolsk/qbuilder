<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionBuilder;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class ConditionBuilderTest
{
    public function testInWithArray(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('role', ['admin', 'user', 'guest'])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`role` IN (\'admin\', \'user\', \'guest\'))');
    }

    public function testInWithString(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('role', 'admin,user,guest')
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`role` IN (\'admin\', \'user\', \'guest\'))');
    }

    public function testInWithEmptyArray(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('role', [])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (1 = 0)');
    }

    public function testInWithNullValues(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('role', ['admin', null, 'user'])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`role` IN (\'admin\', NULL, \'user\'))');
    }

    public function testInWithNumericValues(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('id', [1, 2, 3])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`id` IN (1, 2, 3))');
    }

    public function testInWithBooleanValues(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in('active', [true, false])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`active` IN (1, 0))');
    }

    public function testNotInWithArray(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notIn('role', ['admin', 'user'])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`role` NOT IN (\'admin\', \'user\'))');
    }

    public function testNotInWithEmptyArray(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notIn('role', [])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (1 = 1)');
    }

    public function testInSubquery(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select('user_id')
            ->from('orders')
            ->where()
            ->eq('status', 'completed')
            ->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->inSubquery('id', $subquery)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`id` IN (SELECT `user_id` FROM `orders` WHERE (`status` = '
                . '\'completed\')))'
        );
    }

    public function testInSubqueryWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->insert('users')
            ->insertRow(['name' => 'John'])
        ;

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Subquery for IN must be a SELECT statement')
        ;

        $qb->select('*')
            ->from('users')
            ->where()
            ->inSubquery('id', $invalidSubquery)
            ->end()
        ;
    }

    public function testNotInSubquery(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select('user_id')
            ->from('orders')
            ->where()
            ->eq('status', 'completed')
            ->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notInSubquery('id', $subquery)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`id` NOT IN (SELECT `user_id` FROM `orders` WHERE '
                . '(`status` = \'completed\')))'
        );
    }

    public function testNotInSubqueryWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->update('users')
            ->updateRow(['name' => 'John'])
        ;

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Subquery for NOT IN must be a SELECT statement')
        ;

        $qb->select('*')
            ->from('users')
            ->where()
            ->notInSubquery('id', $invalidSubquery)
            ->end()
        ;
    }

    public function testExists(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select(Field::set('1'))
            ->from('orders')
            ->where()
            ->eqField('users', 'id', 'orders', 'user_id')
            ->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->exists($subquery)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (EXISTS (SELECT 1 FROM `orders` WHERE (`users`.`id` = '
                . '`orders`.`user_id`)))'
        );
    }

    public function testExistsWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->delete('orders')
        ;

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Subquery for EXISTS must be a SELECT statement')
        ;

        $qb->select('*')
            ->from('users')
            ->where()
            ->exists($invalidSubquery)
            ->end()
        ;
    }

    public function testNotExists(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select(Field::set('1'))
            ->from('orders')
            ->where()
            ->eqField('users', 'id', 'orders', 'user_id')
            ->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notExists($subquery)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (NOT EXISTS (SELECT 1 FROM `orders` WHERE (`users`.`id` = '
                . '`orders`.`user_id`)))'
        );
    }

    public function testNotExistsWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->delete('orders')
        ;

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Subquery for NOT EXISTS must be a SELECT statement')
        ;

        $qb->select('*')
            ->from('users')
            ->where()
            ->notExists($invalidSubquery)
            ->end()
        ;
    }

    public function testCompareSubqueryEquals(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select(Field::set('MAX(price)'))
            ->from('orders')
        ;

        $sql = $qb->select('*')
            ->from('products')
            ->where()
            ->compareSubquery('price', '=', $subquery)
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`price` = (SELECT MAX(`price`) FROM `orders`))');
    }

    public function testCompareSubqueryGreaterThan(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select(Field::set('MAX(price)'))
            ->from('orders')
        ;

        $sql = $qb->select('*')
            ->from('products')
            ->where()
            ->compareSubquery('price', '>', $subquery)
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`price` > (SELECT MAX(`price`) FROM `orders`))');
    }

    public function testCompareSubqueryGreaterOrEqual(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select(Field::set('MAX(price)'))
            ->from('orders')
        ;

        $sql = $qb->select('*')
            ->from('products')
            ->where()
            ->compareSubquery('price', '>=', $subquery)
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`price` >= (SELECT MAX(`price`) FROM `orders`))');
    }

    public function testCompareSubqueryLessOrEqual(): void
    {
        $qb = new QueryBuilder();
        $subquery = (new QueryBuilder())
            ->select(Field::set('MAX(price)'))
            ->from('orders')
        ;

        $sql = $qb->select('*')
            ->from('products')
            ->where()
            ->compareSubquery('price', '<=', $subquery)
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`price` <= (SELECT MAX(`price`) FROM `orders`))');
    }

    public function testCompareSubqueryWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->insert('orders')
            ->insertRow(['price' => 100])
        ;

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Subquery for comparison must be a SELECT statement')
        ;

        $qb->select('*')
            ->from('products')
            ->where()
            ->compareSubquery('price', '>', $invalidSubquery)
            ->end()
        ;
    }

    public function testAnd(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->and()
            ->gt('age', 18)
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`status` = \'active\') AND (`age` > 18)');
    }

    public function testOr(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->or()
            ->eq('status', 'pending')
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`status` = \'active\') OR (`status` = \'pending\')');
    }

    public function testAndGroupWithoutClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->andGroup()
            ->eq('role', 'admin')
            ->or()
            ->eq('role', 'moderator')
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`status` = \'active\') AND ((`role` = \'admin\') OR (`role` = '
                . '\'moderator\'))'
        );
    }

    public function testAndGroupWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->andGroup(static function (ConditionBuilder $q): ConditionBuilder {
                return $q->eq('role', 'admin')
                    ->or()
                    ->eq('role', 'moderator')
                ;
            })
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`status` = \'active\') AND ((`role` = \'admin\') OR (`role` = '
                . '\'moderator\'))'
        );
    }

    public function testOrGroupWithoutClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->orGroup()
            ->eq('role', 'admin')
            ->and()
            ->eq('active', 1)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`status` = \'active\') OR ((`role` = \'admin\') AND (`active` '
                . '= 1))'
        );
    }

    public function testOrGroupWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->orGroup(static function (ConditionBuilder $q): ConditionBuilder {
                return $q->eq('role', 'admin')
                    ->and()
                    ->eq('active', 1)
                ;
            })
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`status` = \'active\') OR ((`role` = \'admin\') AND (`active` '
                . '= 1))'
        );
    }

    public function testEndReturnsQueryBuilder(): void
    {
        $qb = new QueryBuilder();
        $returned = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->end()
        ;

        Assert::instanceOf($returned, QueryBuilder::class);
        Assert::same($returned, $qb);
    }

    public function testReset(): void
    {
        $qb = new QueryBuilder();
        $conditionBuilder = $qb->where();
        $conditionBuilder->eq('status', 'active')
            ->and()
            ->gt('age', 18)
        ;

        $conditionBuilder->reset();

        $sql = $qb->build(true);
        Assert::same($sql, '');
    }

    public function testInWithFieldObject(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->in(Field::set('role', 'users'), ['admin', 'user'])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`users`.`role` IN (\'admin\', \'user\'))');
    }

    public function testComplexNestedGroups(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->andGroup()
            ->eq('role', 'admin')
            ->or()
            ->eq('role', 'moderator')
            ->andGroup()
            ->gt('age', 18)
            ->and()
            ->lt('age', 65)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`status` = \'active\') AND ((`role` = \'admin\') OR (`role` = '
                . '\'moderator\') AND ((`age` > 18) AND (`age` < 65)))'
        );
    }
}
