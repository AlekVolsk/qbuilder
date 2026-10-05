<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionJoin;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class JoinConditionQualificationTest
{
    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('provideUnqualifiedFieldsGetJoinAliasCases')]
    public function testUnqualifiedFieldsGetJoinAlias(string $driver, string $expected): void
    {
        $qb = new QueryBuilder($driver);
        $condition = ConditionJoin::create($qb, 'id', 'user_id', 'o')
            ->and()->in('status', ['a'])
            ->and()->isNull('deleted_at')
            ->and()->raw('1 = 1')
        ;

        $sql = $qb->select('*')->from('orders', 'o')->innerJoin('users', 'u', $condition)->build(true);

        Assert::same($sql, $expected);
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function provideUnqualifiedFieldsGetJoinAliasCases(): iterable
    {
        yield 'mysql' => [
            QbConsts::DRIVER_PDO_MYSQL,
            'SELECT `o`.* FROM `orders` AS `o` INNER JOIN `users` AS `u` ON (`u`.`id` = `o`.`user_id`) '
                . "AND (`u`.`status` IN ('a')) AND (`u`.`deleted_at` IS NULL) AND (1 = 1)",
        ];

        yield 'mssql' => [
            QbConsts::DRIVER_MSSQL,
            'SELECT * FROM [orders] AS [o] INNER JOIN [users] AS [u] ON ([u].[id] = [o].[user_id]) '
                . "AND ([u].[status] IN ('a')) AND ([u].[deleted_at] IS NULL) AND (1 = 1)",
        ];

        yield 'postgresql' => [
            QbConsts::DRIVER_PGSQL,
            'SELECT * FROM "orders" AS "o" INNER JOIN "users" AS "u" ON ("u"."id" = "o"."user_id") '
                . 'AND ("u"."status" IN (\'a\')) AND ("u"."deleted_at" IS NULL) AND (1 = 1)',
        ];

        yield 'sqlite' => [
            QbConsts::DRIVER_SQLITE,
            'SELECT * FROM "orders" AS "o" INNER JOIN "users" AS "u" ON ("u"."id" = "o"."user_id") '
                . 'AND ("u"."status" IN (\'a\')) AND ("u"."deleted_at" IS NULL) AND (1 = 1)',
        ];

        yield 'oracle' => [
            QbConsts::DRIVER_ORACLE,
            'SELECT * FROM "ORDERS" "O" INNER JOIN "USERS" "U" ON ("U"."ID" = "O"."USER_ID") '
                . 'AND ("U"."STATUS" IN (\'a\')) AND ("U"."DELETED_AT" IS NULL) AND (1 = 1)',
        ];
    }

    public function testJoinWithoutAliasUsesTableName(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('orders')
            ->leftJoin('users', '', ConditionJoin::create($qb, 'id', 'user_id', 'orders'))
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `orders` LEFT JOIN `users` ON (`users`.`id` = `orders`.`user_id`)');
    }

    public function testStringLiteralsAreNotTouched(): void
    {
        $qb = new QueryBuilder();
        $condition = ConditionJoin::create($qb, 'id', 'user_id', 'o')
            ->and()->eq('name', '(`x` = 1')
            ->and()->eq('code', '__RAW__password')
        ;

        $sql = $qb->select('*')->from('orders', 'o')->innerJoin('users', 'u', $condition)->build(true);

        Assert::same(
            $sql,
            'SELECT `o`.* FROM `orders` AS `o` INNER JOIN `users` AS `u` ON (`u`.`id` = '
                . '`o`.`user_id`) AND (`u`.`name` = \'(`x` = 1\') AND (`u`.`code` = \'__RAW__password\')'
        );
    }
}
