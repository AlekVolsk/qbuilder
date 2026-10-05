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
final class ConditionJoinTest
{
    public function testCreateWithSimpleCondition(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');

        Assert::instanceOf($join, ConditionJoin::class);
        Assert::same($join->build(), ' (`user_id` = `users`.`id`)');
    }

    public function testCreateWithoutParameters(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);

        Assert::instanceOf($join, ConditionJoin::class);
        Assert::false($join->hasConditions());
    }

    public function testConstructorWithSimpleCondition(): void
    {
        $qb = new QueryBuilder();
        $join = new ConditionJoin($qb, 'user_id', 'id', 'users');

        Assert::instanceOf($join, ConditionJoin::class);
        Assert::true($join->hasConditions());
    }

    public function testConstructorWithArrayTarget(): void
    {
        $qb = new QueryBuilder();
        $join = new ConditionJoin($qb, 'user_id', ['id', 'users']);

        Assert::instanceOf($join, ConditionJoin::class);
        Assert::true($join->hasConditions());
    }

    public function testSetAndGetJoinAlias(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);

        $join->setJoinAlias('u');
        Assert::same($join->getJoinAlias(), 'u');

        $join->setJoinAlias('users');
        Assert::same($join->getJoinAlias(), 'users');
    }

    public function testAndSetsAndLogic(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->eq('status', 'active');

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) AND (`status` = \'active\')');
    }

    public function testOrSetsOrLogic(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->or()->eq('status', 'active');

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) OR (`status` = \'active\')');
    }

    public function testMultipleConditionsWithAnd(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->eq('status', 'active')
            ->and()->neq('deleted', 1)
        ;

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) AND (`status` = \'active\') AND (`deleted` != 1)');
    }

    public function testMultipleConditionsWithOr(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->or()->eq('status', 'active')
            ->or()->eq('status', 'pending')
        ;

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) OR (`status` = \'active\') OR (`status` = \'pending\')');
    }

    public function testEqForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);
        $join->eqField('users', 'user_id', 'users', 'id');

        $sql = $join->build();
        Assert::same($sql, ' (`users`.`user_id` = `users`.`id`)');
    }

    public function testEqFieldForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);
        $join->eqField('orders', 'user_id', 'users', 'id');

        $sql = $join->build();
        Assert::same($sql, ' (`orders`.`user_id` = `users`.`id`)');
    }

    public function testInForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->in('status', ['active', 'pending']);

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) AND (`status` IN (\'active\', \'pending\'))');
    }

    public function testLikeForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->like('name', 'John%');

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) AND (`name` LIKE \'%John!%%\' ESCAPE \'!\')');
    }

    public function testIsNullForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->isNull('deleted_at');

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) AND (`deleted_at` IS NULL)');
    }

    public function testComplexJoinCondition(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->eq('status', 'active')
            ->and()->isNotNull('email')
            ->and()->gt('age', 18)
        ;

        $sql = $join->build();
        Assert::same(
            $sql,
            ' (`user_id` = `users`.`id`) AND (`status` = \'active\') AND (`email` IS NOT NULL) AND '
                . '(`age` > 18)'
        );
    }

    public function testCreateWithFieldObjectAsTarget(): void
    {
        $qb = new QueryBuilder();
        $field = Field::set('id', 'users');
        $join = ConditionJoin::create($qb, 'user_id', $field->name, $field->tableOrAlias);

        Assert::instanceOf($join, ConditionJoin::class);
        Assert::true($join->hasConditions());
    }

    public function testResetClearsConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->eq('status', 'active');

        $join->reset();

        Assert::false($join->hasConditions());
    }

    public function testHasConditionsReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);

        Assert::false($join->hasConditions());

        $join->eqField('users', 'user_id', 'users', 'id');
        Assert::true($join->hasConditions());
    }

    public function testBitmaskForJoinConditions(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MYSQL);
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->bitmask('permissions', [1 => 1, 2 => 0]);

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) AND ((`permissions` & 1 = 1) AND (`permissions` & 2 = 0))');
    }

    public function testBetweenForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->between('age', 18, 65);

        $sql = $join->build();
        Assert::same($sql, ' (`user_id` = `users`.`id`) AND (`age` BETWEEN 18 AND 65)');
    }
}
