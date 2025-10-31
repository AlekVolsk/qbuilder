<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConditionJoinTest extends TestCase
{
    public function testCreateWithSimpleCondition(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');

        self::assertInstanceOf(ConditionJoin::class, $join);
        self::assertStringContainsString('user_id', $join->build());
        self::assertStringContainsString('id', $join->build());
    }

    public function testCreateWithoutParameters(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);

        self::assertInstanceOf(ConditionJoin::class, $join);
        self::assertFalse($join->hasConditions());
    }

    public function testConstructorWithSimpleCondition(): void
    {
        $qb = new QueryBuilder();
        $join = new ConditionJoin($qb, 'user_id', 'id', 'users');

        self::assertInstanceOf(ConditionJoin::class, $join);
        self::assertTrue($join->hasConditions());
    }

    public function testConstructorWithArrayTarget(): void
    {
        $qb = new QueryBuilder();
        $join = new ConditionJoin($qb, 'user_id', ['id', 'users']);

        self::assertInstanceOf(ConditionJoin::class, $join);
        self::assertTrue($join->hasConditions());
    }

    public function testSetAndGetJoinAlias(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);

        $join->setJoinAlias('u');
        self::assertSame('u', $join->getJoinAlias());

        $join->setJoinAlias('users');
        self::assertSame('users', $join->getJoinAlias());
    }

    public function testAndSetsAndLogic(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->eq('status', 'active');

        $sql = $join->build();
        self::assertStringContainsString('AND', $sql);
    }

    public function testOrSetsOrLogic(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->or()->eq('status', 'active');

        $sql = $join->build();
        self::assertStringContainsString('OR', $sql);
    }

    public function testMultipleConditionsWithAnd(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->eq('status', 'active')
            ->and()->neq('deleted', 1)
        ;

        $sql = $join->build();
        self::assertStringContainsString('user_id', $sql);
        self::assertStringContainsString('status', $sql);
        self::assertStringContainsString('deleted', $sql);
    }

    public function testMultipleConditionsWithOr(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->or()->eq('status', 'active')
            ->or()->eq('status', 'pending')
        ;

        $sql = $join->build();
        self::assertStringContainsString('user_id', $sql);
        self::assertStringContainsString('status', $sql);
    }

    public function testEqForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);
        $join->eqField('users', 'user_id', 'users', 'id');

        $sql = $join->build();
        self::assertStringContainsString('user_id', $sql);
        self::assertStringContainsString('id', $sql);
    }

    public function testEqFieldForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);
        $join->eqField('orders', 'user_id', 'users', 'id');

        $sql = $join->build();
        self::assertStringContainsString('orders', $sql);
        self::assertStringContainsString('users', $sql);
    }

    public function testInForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->in('status', ['active', 'pending']);

        $sql = $join->build();
        self::assertStringContainsString('IN', $sql);
        self::assertStringContainsString('active', $sql);
    }

    public function testLikeForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->like('name', 'John%');

        $sql = $join->build();
        self::assertStringContainsString('LIKE', $sql);
    }

    public function testIsNullForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->isNull('deleted_at');

        $sql = $join->build();
        self::assertStringContainsString('IS NULL', $sql);
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
        self::assertStringContainsString('user_id', $sql);
        self::assertStringContainsString('status', $sql);
        self::assertStringContainsString('email', $sql);
        self::assertStringContainsString('age', $sql);
    }

    public function testCreateWithFieldObjectAsTarget(): void
    {
        $qb = new QueryBuilder();
        $field = Field::set('id', 'users');
        $join = ConditionJoin::create($qb, 'user_id', $field->name, $field->tableOrAlias);

        self::assertInstanceOf(ConditionJoin::class, $join);
        self::assertTrue($join->hasConditions());
    }

    public function testResetClearsConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->eq('status', 'active');

        $join->reset();

        self::assertFalse($join->hasConditions());
    }

    public function testHasConditionsReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb);

        self::assertFalse($join->hasConditions());

        $join->eqField('users', 'user_id', 'users', 'id');
        self::assertTrue($join->hasConditions());
    }

    public function testBitmaskForJoinConditions(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MYSQL);
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->bitmask('permissions', [1 => 1, 2 => 0]);

        $sql = $join->build();
        self::assertStringContainsString('&', $sql);
    }

    public function testBetweenForJoinConditions(): void
    {
        $qb = new QueryBuilder();
        $join = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $join->and()->between('age', 18, 65);

        $sql = $join->build();
        self::assertStringContainsString('BETWEEN', $sql);
    }
}
