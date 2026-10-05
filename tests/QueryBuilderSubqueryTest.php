<?php

declare(strict_types=1);

namespace QBuilder\Tests;

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
final class QueryBuilderSubqueryTest
{
    public function testSelectSubquery(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('COUNT(*)'))
            ->from('orders')
            ->where()->eqField('orders', 'user_id', 'users', 'id')->end()
        ;

        $sql = $qb->select('id', 'name', Field::subquery($subquery, 'order_count'))
            ->from('users')
            ->build(true)
        ;

        $expected = 'SELECT `id`, `name`, '
            . '(SELECT COUNT(*) FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)) '
            . 'AS `order_count` '
            . 'FROM `users`';

        Assert::same($sql, $expected);
    }

    public function testWhereInSubquery(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('orders')
            ->where()->gte('total', 1000)->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->inSubquery('id', $subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM `users` WHERE (`id` IN '
            . '(SELECT `user_id` FROM `orders` WHERE (`total` >= 1000)))';

        Assert::same($sql, $expected);
    }

    public function testWhereNotInSubquery(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('orders')
            ->where()->eq('status', 'cancelled')->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->notInSubquery('id', $subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM `users` WHERE (`id` NOT IN '
            . '(SELECT `user_id` FROM `orders` WHERE (`status` = \'cancelled\')))';

        Assert::same($sql, $expected);
    }

    public function testWhereExists(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('1'))
            ->from('orders')
            ->where()
            ->eqField('orders', 'user_id', 'users', 'id')
            ->and()->gte('total', 1000)
            ->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->exists($subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM `users` WHERE (EXISTS '
            . '(SELECT 1 FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`) AND (`total` >= 1000)))';

        Assert::same($sql, $expected);
    }

    public function testWhereNotExists(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('1'))
            ->from('orders')
            ->where()->eqField('orders', 'user_id', 'users', 'id')->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->notExists($subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM `users` WHERE (NOT EXISTS '
            . '(SELECT 1 FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)))';

        Assert::same($sql, $expected);
    }

    public function testCompareSubqueryGreaterThan(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('AVG(total)'))
            ->from('orders')
            ->where()->eqField('orders', 'user_id', 'users', 'id')->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->compareSubquery('balance', '>', $subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM `users` WHERE (`balance` > '
            . '(SELECT AVG(`total`) FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)))';

        Assert::same($sql, $expected);
    }

    public function testCompareSubqueryEquals(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()->select(Field::set('MAX(created_at)'))->from('orders');

        $sql = $qb->select('*')
            ->from('orders')
            ->where()->compareSubquery('created_at', '=', $subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM `orders` WHERE (`created_at` = '
            . '(SELECT MAX(`created_at`) FROM `orders`))';

        Assert::same($sql, $expected);
    }

    public function testNestedSubqueries(): void
    {
        $qb = new QueryBuilder();

        $innerSubquery = $qb->subQuery()
            ->select('user_id')
            ->from('orders')
            ->where()->gte('total', 1000)->end()
        ;

        $outerSubquery = $qb->subQuery()
            ->select('department_id')
            ->from('users')
            ->where()->inSubquery('id', $innerSubquery)->end()
        ;

        $sql = $qb->select('*')
            ->from('departments')
            ->where()->inSubquery('id', $outerSubquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM `departments` WHERE (`id` IN '
            . '(SELECT `department_id` FROM `users` WHERE (`id` IN '
            . '(SELECT `user_id` FROM `orders` WHERE (`total` >= 1000)))))';

        Assert::same($sql, $expected);
    }

    public function testSubqueryInvalidTypeThrowsException(): void
    {
        $qb = new QueryBuilder();

        $invalidSubquery = $qb->update('users')->updateRow(['status' => 'active']);

        Expect::exception(InvalidQueryException::class)->withMessageContaining('must be a SELECT statement');

        Field::subquery($invalidSubquery, 'alias');
    }

    public function testInSubqueryInvalidTypeThrowsException(): void
    {
        $qb = new QueryBuilder();

        $qb2 = $qb->subQuery();
        $invalidSubquery = $qb2->delete('users')->where()->eq('id', 1)->end();

        Expect::exception(InvalidQueryException::class)->withMessageContaining('must be a SELECT statement');

        $qb->select('*')
            ->from('users')
            ->where()->inSubquery('id', $invalidSubquery)->end()
            ->build(true)
        ;
    }
}
