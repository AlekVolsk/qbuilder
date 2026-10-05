<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class ConditionAdvancedTest
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`age` NOT BETWEEN 18 AND 65)');
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`age` NOT BETWEEN 18 AND 65)');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (1 = 1)');
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

        Assert::same(
            $sql,
            'SELECT * FROM `users` WHERE (`status` = \'active\') AND (EXISTS (SELECT 1 FROM orders '
                . 'WHERE orders.user_id = users.id))'
        );
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`age` BETWEEN 18 AND 65)');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`created_at` BETWEEN \'2020-01-01\' AND \'2024-12-31\')');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`created_at` NOT BETWEEN \'2020-01-01\' AND \'2024-12-31\')');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (1 = 0)');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (1 = 1)');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`status` IN (\'active\', \'pending\', \'verified\'))');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`status` NOT IN (\'banned\', \'deleted\'))');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`email` LIKE \'%example.com\')');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`name` LIKE \'John%\')');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`name` NOT LIKE \'%Admin%\')');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`email` NOT LIKE \'%example.com\')');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`name` NOT LIKE \'John%\')');
    }

    public function testHasConditionsReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        Assert::false($where->hasConditions());

        $where->eq('status', 'active');

        Assert::true($where->hasConditions());
    }

    public function testResetClearsConditions(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();
        $where->eq('status', 'active')
            ->gt('age', 18)
        ;

        Assert::true($where->hasConditions());

        $where->reset();

        Assert::false($where->hasConditions());
        Assert::same($where->build(), '');
    }

    public function testBuildReturnsEmptyStringForEmptyConditions(): void
    {
        $qb = new QueryBuilder();
        $where = $qb->where();

        Assert::same($where->build(), '');
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

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`price` BETWEEN 10.5 AND 99.99)');
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

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`price` NOT BETWEEN 10.5 AND 99.99)');
    }

    public function testInKeepsEmptyStringArrayElements(): void
    {
        $sql = (new QueryBuilder())->select('*')
            ->from('users')
            ->where()
            ->in('status', ['active', '', 'pending'])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM `users` WHERE (`status` IN ('active', '', 'pending'))");
    }

    public function testNotInKeepsEmptyStringArrayElements(): void
    {
        $sql = (new QueryBuilder())->select('*')
            ->from('users')
            ->where()
            ->notIn('status', [''])
            ->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM `users` WHERE (`status` NOT IN (''))");
    }

    public function testInDropsEmptyElementsOfCommaSeparatedString(): void
    {
        $sql = (new QueryBuilder())->select('*')
            ->from('users')
            ->where()
            ->in('status', 'active,,pending,')
            ->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM `users` WHERE (`status` IN ('active', 'pending'))");
    }

    public function testNotInWithOnlyEmptyCommaSeparatedStringIsAlwaysTrue(): void
    {
        $sql = (new QueryBuilder())->select('*')
            ->from('users')
            ->where()
            ->notIn('status', ',,')
            ->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (1 = 1)');
    }

    public function testInSameOutputForWhereHavingAndJoin(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'u')->in('type', ['0012', 7, null]);
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->leftJoin('orders', 'o', $join)
            ->where()->in('code', ['0012', 7, null])->end()
            ->having()->in('grp', ['0012', 7, null])->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT `u`.* FROM `users` AS `u` LEFT JOIN `orders` AS `o` ON (`o`.`user_id` = '
                . '`u`.`id`) AND (`o`.`type` IN (\'0012\', 7, NULL)) WHERE (`code` IN (\'0012\', 7, NULL)) '
                . 'HAVING (`grp` IN (\'0012\', 7, NULL))'
        );
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`deleted_at` IS NULL)');
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`email` IS NOT NULL)');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`deleted_at` IS NULL)');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`email` IS NOT NULL)');
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

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`deleted_at` IS NULL) AND (`email` IS NOT NULL)');
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`status` = \'active\')');
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`status` != \'inactive\')');
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

        Assert::same($sql, 'SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` > 100)');
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

        Assert::same($sql, 'SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` >= 100)');
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

        Assert::same($sql, 'SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` < 1000)');
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

        Assert::same($sql, 'SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` <= 1000)');
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`name` LIKE \'John%\')');
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

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`email` NOT LIKE \'%test%\')');
    }
}
