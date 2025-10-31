<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBuilder;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConditionBuilderTest extends TestCase
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

        self::assertStringContainsString('`role` IN (\'admin\', \'user\', \'guest\')', $sql);
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

        self::assertStringContainsString('`role` IN (\'admin\', \'user\', \'guest\')', $sql);
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

        self::assertStringContainsString('1 = 0', $sql);
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

        self::assertStringContainsString('NULL', $sql);
        self::assertStringContainsString('`role` IN', $sql);
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

        self::assertStringContainsString('`id` IN (1, 2, 3)', $sql);
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

        self::assertStringContainsString('`active` IN (1, 0)', $sql);
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

        self::assertStringContainsString('`role` NOT IN (\'admin\', \'user\')', $sql);
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

        self::assertStringContainsString('1 = 1', $sql);
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

        self::assertStringContainsString('`id` IN (SELECT', $sql);
        self::assertStringContainsString('FROM `orders`', $sql);
    }

    public function testInSubqueryWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->insert('users')
            ->insertRow(['name' => 'John'])
        ;

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Subquery for IN must be a SELECT statement');

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

        self::assertStringContainsString('`id` NOT IN (SELECT', $sql);
    }

    public function testNotInSubqueryWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->update('users')
            ->updateRow(['name' => 'John'])
        ;

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Subquery for NOT IN must be a SELECT statement');

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

        self::assertStringContainsString('EXISTS (SELECT', $sql);
    }

    public function testExistsWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->delete('orders')
        ;

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Subquery for EXISTS must be a SELECT statement');

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

        self::assertStringContainsString('NOT EXISTS (SELECT', $sql);
    }

    public function testNotExistsWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->delete('orders')
        ;

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Subquery for NOT EXISTS must be a SELECT statement');

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

        self::assertStringContainsString('`price` = (SELECT', $sql);
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

        self::assertStringContainsString('`price` > (SELECT', $sql);
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

        self::assertStringContainsString('`price` >= (SELECT', $sql);
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

        self::assertStringContainsString('`price` <= (SELECT', $sql);
    }

    public function testCompareSubqueryWithInvalidQueryThrowsException(): void
    {
        $qb = new QueryBuilder();
        $invalidSubquery = (new QueryBuilder())
            ->insert('orders')
            ->insertRow(['price' => 100])
        ;

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Subquery for comparison must be a SELECT statement');

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

        self::assertStringContainsString('(`status` = \'active\') AND (`age` > 18)', $sql);
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

        self::assertStringContainsString('(`status` = \'active\') OR (`status` = \'pending\')', $sql);
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

        self::assertStringContainsString('(`status` = \'active\')', $sql);
        self::assertStringContainsString('(`role` = \'admin\')', $sql);
        self::assertStringContainsString('OR (`role` = \'moderator\')', $sql);
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

        self::assertStringContainsString('(`status` = \'active\')', $sql);
        self::assertStringContainsString('(`role` = \'admin\')', $sql);
        self::assertStringContainsString('OR (`role` = \'moderator\')', $sql);
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

        self::assertStringContainsString('(`status` = \'active\')', $sql);
        self::assertStringContainsString('(`role` = \'admin\')', $sql);
        self::assertStringContainsString('AND (`active` = 1)', $sql);
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

        self::assertStringContainsString('(`status` = \'active\')', $sql);
        self::assertStringContainsString('(`role` = \'admin\')', $sql);
        self::assertStringContainsString('AND (`active` = 1)', $sql);
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

        self::assertInstanceOf(QueryBuilder::class, $returned);
        self::assertSame($qb, $returned);
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
        self::assertStringNotContainsString('status', $sql);
        self::assertStringNotContainsString('age', $sql);
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

        self::assertStringContainsString('`users`.`role` IN', $sql);
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

        self::assertStringContainsString('(`status` = \'active\')', $sql);
        self::assertStringContainsString('(`role` = \'admin\')', $sql);
        self::assertStringContainsString('(`age` > 18)', $sql);
    }
}
