<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class QueryBuilderTest extends TestCase
{
    public function testSelectSimple(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        self::assertSame('SELECT `id`, `name` FROM `users`', $sql);
    }

    public function testSelectWithAlias(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('id', Field::set('name', '', 'user_name'))
            ->from('users')
            ->build(true)
        ;

        self::assertSame('SELECT `id`, `name` AS `user_name` FROM `users`', $sql);
    }

    public function testSelectWithTable(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(Field::set('id', 'users'), Field::set('name', 'users'))
            ->from('users')
            ->build(true)
        ;

        self::assertSame('SELECT `users`.`id`, `users`.`name` FROM `users`', $sql);
    }

    public function testSelectExpression(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('id', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->build(true)
        ;

        self::assertSame('SELECT `id`, COUNT(*) AS `total` FROM `users`', $sql);
    }

    public function testSelectAll(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('users')->build(true);

        self::assertSame('SELECT * FROM `users`', $sql);
    }

    public function testFromWithAlias(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(Field::set('id', 'u'), Field::set('name', 'u'))
            ->from('users', 'u')
            ->build(true)
        ;

        self::assertSame('SELECT `u`.`id`, `u`.`name` FROM `users` AS `u`', $sql);
    }

    public function testWhereSimple(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->eq('status', 'active')->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` = \'active\')', $sql);
    }

    public function testWhereMultiple(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->and()->gt('age', 18)
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` = \'active\') AND (`age` > 18)', $sql);
    }

    public function testWhereOr(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->or()->eq('status', 'pending')
            ->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` = \'active\') OR (`status` = \'pending\')', $sql);
    }

    public function testWhereIn(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->in('status', ['active', 'pending'])->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` IN (\'active\', \'pending\'))', $sql);
    }

    public function testWhereNotIn(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->notIn('status', ['banned', 'deleted'])->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` NOT IN (\'banned\', \'deleted\'))', $sql);
    }

    public function testWhereBetween(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->between('age', 18, 65)->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`age` BETWEEN 18 AND 65)', $sql);
    }

    public function testWhereLike(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', 'John', QbConsts::LIKE_FULL)->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`name` LIKE \'%John%\')', $sql);
    }

    public function testWhereIsNull(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->isNull('deleted_at')->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`deleted_at` IS NULL)', $sql);
    }

    public function testWhereIsNotNull(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->isNotNull('email')->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`email` IS NOT NULL)', $sql);
    }

    public function testOrderBy(): void
    {
        $qb = new QueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('name');

        $sql = $qb->select('*')->from('users')->orderBy($orderBy)->build(true);

        self::assertSame('SELECT * FROM `users` ORDER BY `name` ASC', $sql);
    }

    public function testOrderByMultiple(): void
    {
        $qb = new QueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('status')->desc('name');

        $sql = $qb->select('*')->from('users')->orderBy($orderBy)->build(true);

        self::assertSame('SELECT * FROM `users` ORDER BY `status` ASC, `name` DESC', $sql);
    }

    public function testGroupBy(): void
    {
        $qb = new QueryBuilder();
        $groupBy = ConditionBy::groupBy()->add('status');

        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->groupBy($groupBy)
            ->build(true)
        ;

        self::assertSame('SELECT `status`, COUNT(*) AS `total` FROM `users` GROUP BY `status`', $sql);
    }

    public function testHaving(): void
    {
        $qb = new QueryBuilder();
        $groupBy = ConditionBy::groupBy()->add('status');

        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->groupBy($groupBy)
            ->having()->gt(Field::set('COUNT(*)'), 5)->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT `status`, COUNT(*) AS `total` FROM `users` '
            .'GROUP BY `status` HAVING (COUNT(*) > 5)',
            $sql
        );
    }

    public function testLimit(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10)->build(true);

        self::assertSame('SELECT * FROM `users` LIMIT 10', $sql);
    }

    public function testLimitOffset(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10, 20)->build(true);

        self::assertSame('SELECT * FROM `users` LIMIT 20, 10', $sql);
    }

    public function testLimitWithTies(): void
    {
        $qb = new QueryBuilder();

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('MySQL does not support LIMIT WITH TIES');

        $qb->select('name', 'salary')
            ->from('employees')
            ->orderBy(ConditionBy::orderBy()->desc('salary'))
            ->limitWithTies(5)
            ->build(true)
        ;
    }

    public function testInsertSimple(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->insert('users')->insertRow(['name' => 'John', 'age' => 30])->build(true);

        self::assertSame('INSERT INTO `users` (`name`, `age`) VALUES (\'John\', 30)', $sql);
    }

    public function testInsertMultiple(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
            ->build(true)
        ;

        self::assertSame(
            'INSERT INTO `users` (`name`, `age`) VALUES (\'John\', 30), (\'Jane\', 25)',
            $sql
        );
    }

    public function testUpdateSimple(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->update('users')
            ->updateRow(['status' => 'inactive'])
            ->where()->eq('id', 1)->end()
            ->build(true)
        ;

        self::assertSame('UPDATE `users` SET `status` = \'inactive\' WHERE (`id` = 1)', $sql);
    }

    public function testDeleteSimple(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->delete('users')->where()->eq('id', 1)->end()->build(true);

        self::assertSame('DELETE FROM `users` WHERE (`id` = 1)', $sql);
    }

    public function testInnerJoin(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'user_id', 'u', 'id');

        $sql = $qb->select(Field::set('id', 'u'), Field::set('name', 'u'), Field::set('total', 'o'))
            ->from('users', 'u')
            ->innerJoin('orders', 'o', $joinCondition)
            ->build(true)
        ;

        self::assertSame(
            'SELECT `u`.`id`, `u`.`name`, `o`.`total` FROM `users` AS `u` '
            .'INNER JOIN `orders` AS `o` ON (`o`.`user_id` = `u`.`id`)',
            $sql
        );
    }

    public function testLeftJoin(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('p', 'user_id', 'u', 'id');

        $sql = $qb->select(Field::set('id', 'u'), Field::set('name', 'u'), Field::set('phone', 'p'))
            ->from('users', 'u')
            ->leftJoin('phones', 'p', $joinCondition)
            ->build(true)
        ;

        self::assertSame(
            'SELECT `u`.`id`, `u`.`name`, `p`.`phone` FROM `users` AS `u` '
            .'LEFT JOIN `phones` AS `p` ON (`p`.`user_id` = `u`.`id`)',
            $sql
        );
    }

    public function testDriverSetting(): void
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_PDO_MYSQL);

        self::assertSame(QbConsts::DRIVER_PDO_MYSQL, $qb->getDriver());
    }

    public function testDriverInvalidThrowsException(): void
    {
        $qb = new QueryBuilder();

        $this->expectException(UnsupportedFeatureException::class);

        $qb->setDriver('invalid_driver');
    }

    public function testDistinct(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT `user_id` FROM `orders`', $sql);
    }

    public function testDistinctWithMultipleFields(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('user_id', 'product_id')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT `user_id`, `product_id` FROM `orders`', $sql);
    }

    public function testDistinctWithWhere(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('category')->distinct()->from('products')
            ->where()->eq('status', 'active')->end()
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT `category` FROM `products` WHERE (`status` = \'active\')', $sql);
    }

    public function testDistinctWithJoin(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'user_id', 'u', 'id');

        $sql = $qb->select(Field::set('user_id', 'o'), Field::set('name', 'u'))
            ->distinct()
            ->from('orders', 'o')
            ->innerJoin('users', 'u', $joinCondition)
            ->build(true)
        ;

        self::assertSame(
            'SELECT DISTINCT `o`.`user_id`, `u`.`name` FROM `orders` AS `o` '
            .'INNER JOIN `users` AS `u` ON (`o`.`user_id` = `u`.`id`)',
            $sql
        );
    }

    public function testWhereSimpleWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` = \'active\')', $sql);
    }

    public function testWhereMultipleWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(
                static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active')->and()->gt('age', 18)
            )
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` = \'active\') AND (`age` > 18)', $sql);
    }

    public function testWhereOrWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q
                ->eq('status', 'active')
                ->or()
                ->eq('status', 'pending'))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` = \'active\') OR (`status` = \'pending\')', $sql);
    }

    public function testWhereInWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->in('status', ['active', 'pending']))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` IN (\'active\', \'pending\'))', $sql);
    }

    public function testWhereNotInWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->notIn('status', ['banned', 'deleted']))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`status` NOT IN (\'banned\', \'deleted\'))', $sql);
    }

    public function testWhereBetweenWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->between('age', 18, 65))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`age` BETWEEN 18 AND 65)', $sql);
    }

    public function testWhereLikeWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->like('name', 'John', QbConsts::LIKE_FULL))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`name` LIKE \'%John%\')', $sql);
    }

    public function testWhereIsNullWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->isNull('deleted_at'))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`deleted_at` IS NULL)', $sql);
    }

    public function testWhereIsNotNullWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->isNotNull('email'))
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`email` IS NOT NULL)', $sql);
    }

    public function testWhereAndGroupWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(
                static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('country', 'US')
                    ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
                        ->eq('status', 'active')
                        ->or()
                        ->eq('status', 'pending'))
            )
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE (`country` = \'US\') AND '
            .'((`status` = \'active\') OR (`status` = \'pending\'))',
            $sql
        );
    }

    public function testWhereOrGroupWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(
                static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active')
                    ->orGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
                        ->eq('role', 'admin')
                        ->or()
                        ->eq('role', 'moderator'))
            )
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE (`status` = \'active\') OR '
            .'((`role` = \'admin\') OR (`role` = \'moderator\'))',
            $sql
        );
    }

    public function testWhereComplexWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(
                static fn (ConditionBuilder $q): ConditionBuilder => $q
                    ->eq('status', 'active')
                    ->and()
                    ->gt('age', 18)
                    ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
                        ->eq('country', 'US')
                        ->or()
                        ->eq('country', 'CA'))
                    ->and()
                    ->isNotNull('email')
            )
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE (`status` = \'active\') AND (`age` > 18) '
            .'AND ((`country` = \'US\') OR (`country` = \'CA\')) AND (`email` IS NOT NULL)',
            $sql
        );
    }

    public function testHavingWithClosure(): void
    {
        $qb = new QueryBuilder();
        $groupBy = ConditionBy::groupBy()->add('status');

        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->groupBy($groupBy)
            ->having(static fn (ConditionBuilder $q): ConditionBuilder => $q->gt(Field::set('COUNT(*)'), 5))
            ->build(true)
        ;

        self::assertSame(
            'SELECT `status`, COUNT(*) AS `total` FROM `users` '
            .'GROUP BY `status` HAVING (COUNT(*) > 5)',
            $sql
        );
    }

    public function testHavingComplexWithClosure(): void
    {
        $qb = new QueryBuilder();
        $groupBy = ConditionBy::groupBy()->add('status');

        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->groupBy($groupBy)
            ->having(static fn (ConditionBuilder $q): ConditionBuilder => $q
                ->gt(Field::set('COUNT(*)'), 5)->and()->lt(Field::set('SUM(amount)'), 10000))
            ->build(true)
        ;

        self::assertSame(
            'SELECT `status`, COUNT(*) AS `total` FROM `users` '
            .'GROUP BY `status` HAVING (COUNT(*) > 5) AND (SUM(amount) < 10000)',
            $sql
        );
    }

    public function testUpdateWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->update('users')
            ->updateRow(['status' => 'inactive'])
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('id', 1))
            ->build(true)
        ;

        self::assertSame('UPDATE `users` SET `status` = \'inactive\' WHERE (`id` = 1)', $sql);
    }

    public function testDeleteWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->delete('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('id', 1))
            ->build(true)
        ;

        self::assertSame('DELETE FROM `users` WHERE (`id` = 1)', $sql);
    }

    public function testWhereNestedGroupsWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(
                static fn (ConditionBuilder $q): ConditionBuilder => $q
                    ->eq('status', 'active')
                    ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
                        ->eq('country', 'US')
                        ->orGroup(static fn (ConditionBuilder $q3): ConditionBuilder => $q3
                            ->eq('state', 'CA')
                            ->or()
                            ->eq('state', 'NY')))
            )
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE (`status` = \'active\') AND '
            .'((`country` = \'US\') OR ((`state` = \'CA\') OR (`state` = \'NY\')))',
            $sql
        );
    }

    public function testMixedSyntaxWhereAndClosure(): void
    {
        $qb = new QueryBuilder();

        $sql1 = $qb->select('*')
            ->from('users')
            ->where()->eq('status', 'active')->end()
            ->build(true)
        ;

        $qb2 = new QueryBuilder();
        $sql2 = $qb2->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'))
            ->build(true)
        ;

        self::assertSame($sql1, $sql2);
        self::assertSame('SELECT * FROM `users` WHERE (`status` = \'active\')', $sql1);
    }

    public function testWhereBitmaskWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q
                ->bitmask('permissions', [1 => 1, 2 => 1, 4 => 0]))
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE ((`permissions` & 1 = 1) AND '
            .'(`permissions` & 2 = 2) AND (`permissions` & 4 = 0))',
            $sql
        );
    }

    public function testWhereBitmaskComplexWithClosure(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where(
                static fn (ConditionBuilder $q): ConditionBuilder => $q
                    ->eq('status', 'active')
                    ->and()
                    ->bitmask('permissions', [1 => 1, 2 => 1])
                    ->and()
                    ->gt('age', 18)
            )
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE (`status` = \'active\') AND '
            .'((`permissions` & 1 = 1) AND (`permissions` & 2 = 2)) AND (`age` > 18)',
            $sql
        );
    }
}
