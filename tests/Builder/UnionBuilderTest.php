<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Builder\UnionBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class UnionBuilderTest extends TestCase
{
    public function testUnionSimple(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id', 'name')
            ->from('users')
            ->where()->eq('status', 'active')->end()
        ;
        $query2 = $qb->subQuery()->select('id', 'name')
            ->from('users')
            ->where()->eq('status', 'pending')->end()
        ;

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->build();

        $expected = "SELECT `id`, `name`\nFROM `users`\nWHERE (`status` = 'active')\nUNION\n"
            ."SELECT `id`, `name`\nFROM `users`\nWHERE (`status` = 'pending')";

        self::assertSame($expected, $sql);
    }

    public function testUnionAll(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('email')->from('customers');
        $query2 = $qb->subQuery()->select('email')->from('suppliers');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->all()->build();

        $expected = "SELECT `email`\nFROM `customers`\nUNION ALL\nSELECT `email`\nFROM `suppliers`";

        self::assertSame($expected, $sql);
    }

    public function testUnionWithOrderBy(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id', 'name')
            ->from('users')
            ->where()->eq('type', 'admin')->end()
        ;
        $query2 = $qb->subQuery()->select('id', 'name')
            ->from('users')
            ->where()->eq('type', 'moderator')->end()
        ;

        $orderBy = ConditionBy::orderBy()->add('name', '', QbConsts::ORDER_ASC);

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->orderBy($orderBy)->build();

        $expected = "(SELECT `id`, `name`\nFROM `users`\nWHERE (`type` = 'admin')\nUNION\n"
            ."SELECT `id`, `name`\nFROM `users`\nWHERE (`type` = 'moderator'))\n"
            .'ORDER BY `name` ASC';

        self::assertSame($expected, $sql);
    }

    public function testUnionWithLimit(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id', 'title')->from('articles');
        $query2 = $qb->subQuery()->select('id', 'title')->from('news');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->limit(10)->build();

        $expected = "(SELECT `id`, `title`\nFROM `articles`\nUNION\n"
            ."SELECT `id`, `title`\nFROM `news`)\nLIMIT 10";

        self::assertSame($expected, $sql);

        $sql = $union->build(true);

        self::assertSame(
            '(SELECT `id`, `title` FROM `articles` UNION SELECT `id`, `title` FROM `news`) LIMIT 10',
            $sql
        );
    }

    public function testUnionWithOffset(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id')->from('table1');
        $query2 = $qb->subQuery()->select('id')->from('table2');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->limit(10, 20)->build();

        self::assertStringContainsString('LIMIT 10', $sql);
        self::assertStringContainsString('OFFSET 20', $sql);
    }

    public function testUnionWithOrderByAndLimit(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id', 'name')->from('users');
        $query2 = $qb->subQuery()->select('id', 'name')->from('admins');

        $orderBy = ConditionBy::orderBy()->desc('name');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->orderBy($orderBy)->limit(5)->build();

        self::assertStringContainsString('ORDER BY', $sql);
        self::assertStringContainsString('LIMIT 5', $sql);
    }

    public function testBuildReturnsEmptyStringForEmptyQueries(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        self::assertSame('', $union->build());
    }

    public function testBuildReturnsSingleQueryWithoutUnion(): void
    {
        $qb = new QueryBuilder();
        $query = $qb->select('id')->from('users');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query)->build();

        self::assertStringNotContainsString('UNION', $sql);
        self::assertStringContainsString('SELECT', $sql);
    }

    public function testAddWithStringQuery(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        $union->add('SELECT id FROM users');
        $union->add('SELECT id FROM admins');

        $sql = $union->build();
        self::assertStringContainsString('SELECT id FROM users', $sql);
        self::assertStringContainsString('SELECT id FROM admins', $sql);
        self::assertStringContainsString('UNION', $sql);
    }

    public function testAllWithFalseParameter(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id')->from('users');
        $query2 = $qb->subQuery()->select('id')->from('admins');

        $union = new UnionBuilder($qb);
        $union->all(true);
        $sql1 = $union->add($query1)->add($query2)->build();

        $union2 = new UnionBuilder($qb);
        $union2->all(false);
        $sql2 = $union2->add($query1)->add($query2)->build();

        self::assertStringContainsString('UNION ALL', $sql1);
        self::assertStringContainsString('UNION', $sql2);
        self::assertStringNotContainsString('UNION ALL', $sql2);
    }

    public function testLimitWithZeroRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $query = $qb->select('id')->from('users');

        $union = new UnionBuilder($qb);
        $union->add($query)->limit(10)->limit(0);

        $sql = $union->build();
        self::assertStringNotContainsString('LIMIT', $sql);
    }

    public function testLimitWithNegativeRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $query = $qb->select('id')->from('users');

        $union = new UnionBuilder($qb);
        $union->add($query)->limit(10)->limit(-1);

        $sql = $union->build();
        self::assertStringNotContainsString('LIMIT', $sql);
    }

    public function testResetClearsAllQueries(): void
    {
        $qb = new QueryBuilder();
        $query1 = $qb->select('id')->from('users');
        $query2 = $qb->subQuery()->select('id')->from('admins');

        $union = new UnionBuilder($qb);
        $union->add($query1)->add($query2)->all(true)->limit(10);

        self::assertFalse($union->isEmpty());
        self::assertSame(2, $union->count());

        $union->reset();

        self::assertTrue($union->isEmpty());
        self::assertSame(0, $union->count());
        self::assertSame('', $union->build());
    }

    public function testCountReturnsNumberOfQueries(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        self::assertSame(0, $union->count());

        $union->add($qb->select('id')->from('users'));
        self::assertSame(1, $union->count());

        $union->add($qb->subQuery()->select('id')->from('admins'));
        self::assertSame(2, $union->count());
    }

    public function testIsEmptyReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        self::assertTrue($union->isEmpty());

        $union->add($qb->select('id')->from('users'));
        self::assertFalse($union->isEmpty());
    }

    public function testBuildWithCompactFlag(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id', 'name')->from('users');
        $query2 = $qb->subQuery()->select('id', 'name')->from('admins');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->build(true);

        self::assertStringNotContainsString("\n\n", $sql);
        self::assertStringContainsString('UNION', $sql);
    }

    public function testUnionWithMultipleQueries(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id')->from('table1');
        $query2 = $qb->subQuery()->select('id')->from('table2');
        $query3 = $qb->subQuery()->select('id')->from('table3');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->add($query3)->build();

        $unionCount = substr_count($sql, 'UNION');
        self::assertSame(2, $unionCount);
    }
}
