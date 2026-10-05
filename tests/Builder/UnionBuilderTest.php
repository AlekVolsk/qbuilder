<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Builder\UnionBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class UnionBuilderTest
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
            . "SELECT `id`, `name`\nFROM `users`\nWHERE (`status` = 'pending')";

        Assert::same($sql, $expected);
    }

    public function testUnionAll(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('email')->from('customers');
        $query2 = $qb->subQuery()->select('email')->from('suppliers');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->all()->build();

        $expected = "SELECT `email`\nFROM `customers`\nUNION ALL\nSELECT `email`\nFROM `suppliers`";

        Assert::same($sql, $expected);
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

        $expected = "SELECT `id`, `name`\nFROM `users`\nWHERE (`type` = 'admin')\nUNION\n"
            . "SELECT `id`, `name`\nFROM `users`\nWHERE (`type` = 'moderator')\n"
            . 'ORDER BY `name` ASC';

        Assert::same($sql, $expected);
    }

    public function testUnionWithLimit(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id', 'title')->from('articles');
        $query2 = $qb->subQuery()->select('id', 'title')->from('news');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->limit(10)->build();

        $expected = "SELECT `id`, `title`\nFROM `articles`\nUNION\n"
            . "SELECT `id`, `title`\nFROM `news`\nLIMIT 10";

        Assert::same($sql, $expected);

        $sql = $union->build(true);

        Assert::same($sql, 'SELECT `id`, `title` FROM `articles` UNION SELECT `id`, `title` FROM `news` LIMIT 10');
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('provideUnionOrderByAndLimitPerDialectCases')]
    public function testUnionOrderByAndLimitPerDialect(string $driver, string $expected): void
    {
        $qb = new QueryBuilder($driver);
        $union = (new UnionBuilder($qb))
            ->add($qb->subQuery()->select('id')->from('users'))
            ->add($qb->subQuery()->select('id')->from('admins'))
            ->orderBy(ConditionBy::orderBy()->desc('id'))
            ->limit(5, 10)
        ;

        Assert::same($union->build(true), $expected);
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function provideUnionOrderByAndLimitPerDialectCases(): iterable
    {
        yield 'MySQL' => [
            QbConsts::DRIVER_PDO_MYSQL,
            'SELECT `id` FROM `users` UNION SELECT `id` FROM `admins` ORDER BY `id` DESC LIMIT 10, 5',
        ];

        yield 'PostgreSQL' => [
            QbConsts::DRIVER_PGSQL,
            'SELECT "id" FROM "users" UNION SELECT "id" FROM "admins" ORDER BY "id" DESC LIMIT 5 OFFSET 10',
        ];

        yield 'SQLite' => [
            QbConsts::DRIVER_SQLITE,
            'SELECT "id" FROM "users" UNION SELECT "id" FROM "admins" ORDER BY "id" DESC LIMIT 5 OFFSET 10',
        ];

        yield 'MS SQL Server' => [
            QbConsts::DRIVER_MSSQL,
            'SELECT [id] FROM [users] UNION SELECT [id] FROM [admins] ORDER BY [id] DESC '
                . 'OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY',
        ];

        yield 'Oracle' => [
            QbConsts::DRIVER_ORACLE,
            'SELECT "ID" FROM "USERS" UNION SELECT "ID" FROM "ADMINS" ORDER BY "ID" DESC '
                . 'OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY',
        ];

        yield 'ClickHouse' => [
            QbConsts::DRIVER_CLICKHOUSE,
            'SELECT * FROM ( SELECT `id` FROM `users` UNION DISTINCT SELECT `id` FROM `admins` ) '
                . 'ORDER BY `id` DESC LIMIT 10, 5',
        ];
    }

    public function testMssqlUnionLimitWithoutOrderByGetsNeutralOrder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $union = (new UnionBuilder($qb))
            ->add($qb->subQuery()->select('id')->from('users'))
            ->add($qb->subQuery()->select('id')->from('admins'))
            ->all()
            ->limit(5)
        ;

        Assert::same(
            $union->build(true),
            'SELECT [id] FROM [users] UNION ALL SELECT [id] FROM [admins] ORDER BY (SELECT NULL) '
                . 'OFFSET 0 ROWS FETCH NEXT 5 ROWS ONLY'
        );
    }

    public function testBuildReturnsEmptyStringForEmptyQueries(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        Assert::same($union->build(), '');
    }

    public function testBuildReturnsSingleQueryWithoutUnion(): void
    {
        $qb = new QueryBuilder();
        $query = $qb->select('id')->from('users');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query)->build();

        Assert::same($sql, "SELECT `id`\nFROM `users`");
    }

    public function testAddWithStringQuery(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        $union->add('SELECT id FROM users');
        $union->add('SELECT id FROM admins');

        $sql = $union->build();
        Assert::same($sql, "SELECT id FROM users\nUNION\nSELECT id FROM admins");
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

        Assert::same($sql1, "SELECT `id`\nFROM `users`\nUNION ALL\nSELECT `id`\nFROM `admins`");
        Assert::same($sql2, "SELECT `id`\nFROM `users`\nUNION\nSELECT `id`\nFROM `admins`");
    }

    public function testLimitWithZeroRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $query = $qb->select('id')->from('users');

        $union = new UnionBuilder($qb);
        $union->add($query)->limit(10)->limit(0);

        $sql = $union->build();
        Assert::same($sql, "SELECT `id`\nFROM `users`");
    }

    public function testLimitWithNegativeRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $query = $qb->select('id')->from('users');

        $union = new UnionBuilder($qb);
        $union->add($query)->limit(10)->limit(-1);

        $sql = $union->build();
        Assert::same($sql, "SELECT `id`\nFROM `users`");
    }

    public function testResetClearsAllQueries(): void
    {
        $qb = new QueryBuilder();
        $query1 = $qb->select('id')->from('users');
        $query2 = $qb->subQuery()->select('id')->from('admins');

        $union = new UnionBuilder($qb);
        $union->add($query1)->add($query2)->all(true)->limit(10);

        Assert::false($union->isEmpty());
        Assert::same($union->count(), 2);

        $union->reset();

        Assert::true($union->isEmpty());
        Assert::same($union->count(), 0);
        Assert::same($union->build(), '');
    }

    public function testCountReturnsNumberOfQueries(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        Assert::same($union->count(), 0);

        $union->add($qb->select('id')->from('users'));
        Assert::same($union->count(), 1);

        $union->add($qb->subQuery()->select('id')->from('admins'));
        Assert::same($union->count(), 2);
    }

    public function testIsEmptyReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        $union = new UnionBuilder($qb);

        Assert::true($union->isEmpty());

        $union->add($qb->select('id')->from('users'));
        Assert::false($union->isEmpty());
    }

    public function testBuildWithCompactFlag(): void
    {
        $qb = new QueryBuilder();

        $query1 = $qb->select('id', 'name')->from('users');
        $query2 = $qb->subQuery()->select('id', 'name')->from('admins');

        $union = new UnionBuilder($qb);
        $sql = $union->add($query1)->add($query2)->build(true);

        Assert::same($sql, 'SELECT `id`, `name` FROM `users` UNION SELECT `id`, `name` FROM `admins`');
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
        Assert::same($unionCount, 2);
    }
}
