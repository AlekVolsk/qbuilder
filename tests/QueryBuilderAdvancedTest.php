<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Builder\RecursiveCteBuilder;
use QBuilder\Builder\UnionBuilder;
use QBuilder\Condition\ConditionBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class QueryBuilderAdvancedTest
{
    public function testSetDriverChangesDriver(): void
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_POSTGRESQL);

        Assert::same($qb->getDriver(), QbConsts::DRIVER_POSTGRESQL);
    }

    public function testSetDriverWithInvalidDriverThrowsException(): void
    {
        $qb = new QueryBuilder();

        Expect::exception(UnsupportedFeatureException::class)->withMessageContaining('Unsupported database driver');

        $qb->setDriver('invalid_driver');
    }

    public function testShowQueryReturnsEmptyStringForEmptyQuery(): void
    {
        $qb = new QueryBuilder();

        Assert::same($qb->showQuery(), '');
    }

    public function testShowQueryReturnsQueryForNonEmptyQuery(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users');

        $sql = $qb->showQuery();
        Assert::same($sql, "SELECT *\nFROM `users`");
    }

    public function testInsertFromWithSubquery(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('name', 'email', 'age')
            ->from('temp_users')
            ->where()
            ->eq('verified', 1)
            ->end()
        ;

        $sql = $qb->insert('users')
            ->insertFrom($subquery, ['name', 'email', 'age'])
            ->build(true)
        ;

        Assert::same(
            $sql,
            'INSERT INTO `users` (`name`, `email`, `age`) SELECT `name`, `email`, `age` FROM '
                . '`temp_users` WHERE (`verified` = 1)'
        );
    }

    public function testInsertFromWithoutFields(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('name', 'email')
            ->from('temp_users')
        ;

        $sql = $qb->insert('users')
            ->insertFrom($subquery)
            ->build(true)
        ;

        Assert::same($sql, 'INSERT INTO `users` SELECT `name`, `email` FROM `temp_users`');
    }

    public function testUpdateFromSelectWithSubquery(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_updates')
            ->where()
            ->eq('status', 1)
            ->end()
        ;

        $sql = $qb->updateFromSelect('users', $subquery, ['status' => 'verified'], 'id')
            ->build(true)
        ;

        Assert::same(
            $sql,
            'UPDATE `users` SET `status` = \'verified\' WHERE `id` IN ( SELECT `user_id` FROM '
                . '`temp_updates` WHERE (`status` = 1) )'
        );
    }

    public function testDeleteFromSelectWithSubquery(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_deletions')
            ->where()
            ->eq('status', 'deleted')
            ->end()
        ;

        $sql = $qb->deleteFromSelect('users', $subquery, 'id')
            ->build(true)
        ;

        Assert::same(
            $sql,
            'DELETE FROM `users` WHERE `id` IN ( SELECT `user_id` FROM `temp_deletions` WHERE '
                . '(`status` = \'deleted\') )'
        );
    }

    public function testProcedureWithoutParameters(): void
    {
        $qb = new QueryBuilder();
        $qb->procedure('get_all_users');

        Assert::same($qb->getType(), 'PROCEDURE');
        Assert::same($qb->getProcedureName(), 'get_all_users');
    }

    public function testProcedureWithParameters(): void
    {
        $qb = new QueryBuilder();
        $qb->procedure('get_user_by_id', [1, 'active']);

        Assert::same($qb->getType(), 'PROCEDURE');
        Assert::same($qb->getProcedureName(), 'get_user_by_id');
        Assert::same($qb->getProcedureParams(), [1, 'active']);
    }

    public function testUnionReturnsUnionBuilder(): void
    {
        $qb = new QueryBuilder();
        $union = $qb->union();

        Assert::instanceOf($union, UnionBuilder::class);
    }

    public function testRecursiveCteReturnsRecursiveCteBuilder(): void
    {
        $qb = new QueryBuilder();
        $cte = $qb->recursiveCte('category_tree');

        Assert::instanceOf($cte, RecursiveCteBuilder::class);
    }

    public function testConflictBuilderReturnsConflictBuilderInterface(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        Assert::instanceOf($builder, ConflictBuilderInterface::class);
    }

    public function testConflictBuilderThrowsExceptionForUnsupportedDriver(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_CLICKHOUSE);

        Expect::exception(UnsupportedFeatureException::class)
            ->withMessageContaining('ClickHouse does not support conflict handlers')
        ;

        $qb->conflictBuilder();
    }

    public function testInsertConflictHandlerAddsConflictHandler(): void
    {
        $qb = new QueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->set('name', 'John Updated')
            ->increment('visits')
        ;

        $qb->insert('users')
            ->insertRow(['email' => 'john@example.com', 'name' => 'John', 'visits' => 1])
            ->insertConflictHandler($conflictBuilder)
        ;

        Assert::false(empty($qb->getInsertConflictData()));
    }

    public function testFinalForClickHouse(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_CLICKHOUSE);
        $qb->select('*')->from('sensor_data')->final();

        Assert::true($qb->isFromFinal());
    }

    public function testFinalSetsFlagForAllDrivers(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->final();

        Assert::true($qb->isFromFinal());
    }

    public function testFullJoin(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PGSQL);
        $joinCondition = ConditionJoin::create($qb, 'user_id', 'id', 'o');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->fullJoin('users', 'u', $joinCondition)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM "orders" AS "o" FULL JOIN "users" AS "u" ON ("u"."user_id" = "o"."id")');
    }

    /**
     * @param \Closure(QueryBuilder): QueryBuilder $addFullJoin
     */
    #[DataProvider('provideFullJoinIsRejectedOnMysqlCases')]
    public function testFullJoinIsRejectedOnMysql(\Closure $addFullJoin): void
    {
        $qb = $addFullJoin((new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL))->select('*')->from('orders', 'o'));

        Expect::exception(UnsupportedFeatureException::class)->withMessageContaining('does not support FULL JOIN');

        $qb->build();
    }

    /**
     * @return iterable<string, array{\Closure(QueryBuilder): QueryBuilder}>
     */
    public static function provideFullJoinIsRejectedOnMysqlCases(): iterable
    {
        yield 'table' => [
            static fn (QueryBuilder $qb): QueryBuilder => $qb->fullJoin(
                'users',
                'u',
                ConditionJoin::create($qb, 'user_id', 'id', 'o')
            ),
        ];

        yield 'subquery' => [
            static fn (QueryBuilder $qb): QueryBuilder => $qb->fullJoinFromSelect(
                $qb->subQuery()->select('id')->from('users'),
                'u',
                ConditionJoin::create($qb, 'user_id', 'id', 'o')
            ),
        ];
    }

    public function testJoinWithUnknownTypeIsRejected(): void
    {
        $qb = new QueryBuilder();
        $condition = ConditionJoin::create($qb, 'user_id', 'id', 'o');

        Expect::exception(InvalidQueryException::class)->withMessageContaining("Unknown JOIN type 'LEFTT'");

        $qb->select('*')->from('orders', 'o')->join('users', 'u', $condition, 'LEFTT');
    }

    public function testJoinTypeIsCaseInsensitive(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('orders', 'o')
            ->join('users', 'u', ConditionJoin::create($qb, 'user_id', 'id', 'o'), 'left')
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `o`.* FROM `orders` AS `o` LEFT JOIN `users` AS `u` ON (`u`.`user_id` = `o`.`id`)');
    }

    public function testCrossJoin(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = ConditionJoin::create($qb);

        $sql = $qb->select('*')
            ->from('products', 'p')
            ->crossJoin('categories', 'c', $joinCondition)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `p`.* FROM `products` AS `p` CROSS JOIN `categories` AS `c`');
    }

    public function testLeftJoinFromSelect(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id', Field::set('SUM(total)', '', 'total_spent'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('user_id'))
        ;
        $joinCondition = ConditionJoin::create($qb, 'user_id', 'id', 'users');

        $sql = $qb->select('*')
            ->from('users')
            ->leftJoinFromSelect($subquery, 'order_stats', $joinCondition)
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `users` LEFT JOIN (SELECT `user_id`, SUM(`total`) AS `total_spent` FROM '
                . '`orders` GROUP BY `user_id`) AS `order_stats` ON (`order_stats`.`user_id` = '
                . '`users`.`id`)'
        );
    }

    public function testInnerJoinFromSelect(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('product_id', Field::set('AVG(price)', '', 'avg_price'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('product_id'))
        ;
        $joinCondition = ConditionJoin::create($qb, 'product_id', 'id', 'products');

        $sql = $qb->select('*')
            ->from('products')
            ->innerJoinFromSelect($subquery, 'price_stats', $joinCondition)
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `products` INNER JOIN (SELECT `product_id`, AVG(`price`) AS `avg_price` '
                . 'FROM `orders` GROUP BY `product_id`) AS `price_stats` ON (`price_stats`.`product_id` = '
                . '`products`.`id`)'
        );
    }

    public function testRightJoinFromSelect(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('category_id', Field::set('COUNT(*)', '', 'product_count'))
            ->from('products')
            ->groupBy(ConditionBy::groupBy()->add('category_id'))
        ;
        $joinCondition = ConditionJoin::create($qb, 'category_id', 'id', 'categories');

        $sql = $qb->select('*')
            ->from('categories')
            ->rightJoinFromSelect($subquery, 'product_stats', $joinCondition)
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM `categories` RIGHT JOIN (SELECT `category_id`, COUNT(*) AS '
                . '`product_count` FROM `products` GROUP BY `category_id`) AS `product_stats` ON '
                . '(`product_stats`.`category_id` = `categories`.`id`)'
        );
    }

    public function testFullJoinFromSelect(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PGSQL);
        $subquery = $qb->subQuery()
            ->select('region_id', Field::set('SUM(amount)', '', 'total_amount'))
            ->from('sales')
            ->groupBy(ConditionBy::groupBy()->add('region_id'))
        ;
        $joinCondition = ConditionJoin::create($qb, 'region_id', 'id', 'regions');

        $sql = $qb->select('*')
            ->from('regions')
            ->fullJoinFromSelect($subquery, 'sales_stats', $joinCondition)
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT * FROM "regions" FULL JOIN (SELECT "region_id", SUM("amount") AS "total_amount" FROM "sales" '
                . 'GROUP BY "region_id") AS "sales_stats" ON ("sales_stats"."region_id" = "regions"."id")'
        );
    }

    public function testCrossJoinFromSelect(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('*')
            ->from('temp_table')
        ;
        $joinCondition = ConditionJoin::create($qb);

        $sql = $qb->select('*')
            ->from('products')
            ->crossJoinFromSelect($subquery, 'temp', $joinCondition)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` CROSS JOIN (SELECT * FROM `temp_table`) AS `temp`');
    }

    public function testHavingWithClosureSyntax(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having(
                static fn (ConditionBuilder $q): ConditionBuilder => $q->gt(Field::set('COUNT(*)'), 5)
            )
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT `status`, COUNT(*) AS `total` FROM `orders` GROUP BY `status` HAVING (COUNT(*) > '
                . '5)'
        );
    }

    public function testHavingWithStandardSyntax(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having()
            ->gt(Field::set('COUNT(*)'), 5)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT `status`, COUNT(*) AS `total` FROM `orders` GROUP BY `status` HAVING (COUNT(*) > '
                . '5)'
        );
    }

    public function testHavingWithMultipleConditions(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having()
            ->gt(Field::set('COUNT(*)'), 5)
            ->and()
            ->lt(Field::set('SUM(total)'), 10000)
            ->end()
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT `status`, COUNT(*) AS `total` FROM `orders` GROUP BY `status` HAVING (COUNT(*) > '
                . '5) AND (SUM(total) < 10000)'
        );
    }

    public function testFromWithSubquery(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id', Field::set('SUM(total)', '', 'total_spent'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('user_id'))
        ;

        $sql = $qb->select('*')
            ->from($subquery, 'order_stats')
            ->build(true)
        ;

        Assert::same(
            $sql,
            'SELECT `order_stats`.* FROM (SELECT `user_id`, SUM(`total`) AS `total_spent` FROM '
                . '`orders` GROUP BY `user_id`) AS `order_stats`'
        );
    }

    public function testSubQueryCreatesNewQueryBuilderInstance(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery();

        Assert::instanceOf($subquery, QueryBuilder::class);
        Assert::notSame($subquery, $qb);
    }

    public function testSubQueryInheritsDriver(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $subquery = $qb->subQuery();

        Assert::same($subquery->getDriver(), QbConsts::DRIVER_POSTGRESQL);
    }

    public function testLimitWithZeroRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->limit(10)->limit(0);

        Assert::null($qb->getLimitValue());
        Assert::null($qb->getOffsetValue());
    }

    public function testLimitWithNegativeRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->limit(10)->limit(-1);

        Assert::null($qb->getLimitValue());
    }

    public function testLimitWithTiesWithZeroRemovesLimit(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $qb->select('*')
            ->from('users')
            ->orderBy(ConditionBy::orderBy()->asc('name'))
            ->limitWithTies(10)
            ->limitWithTies(0)
        ;

        Assert::null($qb->getLimitValue());
        Assert::false($qb->isLimitWithTies());
    }

    public function testRightJoin(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('orders', 'o')
            ->rightJoin('users', 'u', ConditionJoin::create($qb, 'id', 'user_id', 'o'))
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `o`.* FROM `orders` AS `o` RIGHT JOIN `users` AS `u` ON (`u`.`id` = `o`.`user_id`)');
    }

    public function testCrossJoinWithoutCondition(): void
    {
        $sql = (new QueryBuilder())->select('*')->from('products', 'p')->crossJoin('categories', 'c')->build(true);

        Assert::same($sql, 'SELECT `p`.* FROM `products` AS `p` CROSS JOIN `categories` AS `c`');
    }

    public function testCrossJoinFromSelectWithoutCondition(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('products')
            ->crossJoinFromSelect($qb->subQuery()->select('id')->from('tags'), 't')
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` CROSS JOIN (SELECT `id` FROM `tags`) AS `t`');
    }

    public function testJoinTargetWithoutTableRefersToJoinedTable(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')->from('orders', 'o')
            ->innerJoin('users', 'u', ConditionJoin::create($qb, 'id', 'user_id'))
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `o`.* FROM `orders` AS `o` INNER JOIN `users` AS `u` ON (`u`.`id` = `u`.`user_id`)');
    }

    public function testSelectRejectsExpressionPassedAsFieldName(): void
    {
        Expect::exception(InvalidQueryException::class)->withMessageContaining('Use Field::set() for expressions');

        (new QueryBuilder())->select('"customer" AS type');
    }

    public function testSelectWithoutFieldsSelectsAll(): void
    {
        Assert::same((new QueryBuilder(QbConsts::DRIVER_PGSQL))->select()->from('t')->build(true), 'SELECT * FROM "t"');
    }

    public function testReusedBuilderDropsPreviousWhereAndHaving(): void
    {
        $qb = new QueryBuilder();
        $qb->select('status')->from('orders')
            ->where()->eq('archived', 0)->end()
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having()->gt(Field::set('COUNT(*)'), 1)->end()
            ->build()
        ;

        Assert::same($qb->select('id')->from('users')->build(true), 'SELECT `id` FROM `users`');
    }

    public function testGroupByQualifiedField(): void
    {
        $sql = (new QueryBuilder(QbConsts::DRIVER_PGSQL))->select(Field::set('status', 'o'))->from('orders', 'o')
            ->groupBy(ConditionBy::groupBy()->add('status', 'o'))
            ->build(true)
        ;

        Assert::same($sql, 'SELECT "o"."status" FROM "orders" AS "o" GROUP BY "o"."status"');
    }

    public function testMysqlFieldOfFromTableIsQualifiedWithItsAlias(): void
    {
        $sql = (new QueryBuilder())->select(Field::set('id', 'users'))->from('users', 'u')->build(true);

        Assert::same($sql, 'SELECT `u`.`id` FROM `users` AS `u`');
    }

    #[DataProvider('provideEmptySubqueryAliases')]
    public function testFromSubqueryRequiresAlias(string $alias): void
    {
        $qb = new QueryBuilder();
        $subQuery = $qb->subQuery()->select('id')->from('t');

        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Empty alias name');

        $qb->select('id')->from($subQuery, $alias);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEmptySubqueryAliases(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('provideExpressionFieldsAreNotQuotedCases')]
    public function testExpressionFieldsAreNotQuoted(string $expression, string $alias, string $expected): void
    {
        $sql = (new QueryBuilder(QbConsts::DRIVER_PGSQL))->select(Field::set($expression, '', $alias))
            ->from('t')
            ->build(true)
        ;

        Assert::same($sql, $expected);
    }

    /**
     * @return iterable<string, array{string, string, non-empty-string}>
     */
    public static function provideExpressionFieldsAreNotQuotedCases(): iterable
    {
        yield 'arithmetic' => ['price * qty', 'total', 'SELECT price * qty AS "total" FROM "t"'];

        yield 'CASE' => [
            'CASE WHEN a > 1 THEN 1 ELSE 0 END',
            'flag',
            'SELECT CASE WHEN a > 1 THEN 1 ELSE 0 END AS "flag" FROM "t"',
        ];
    }

    public function testClickhouseSelectWithoutFrom(): void
    {
        Assert::same((new QueryBuilder(QbConsts::DRIVER_CLICKHOUSE))->select('1')->build(true), 'SELECT 1');
    }
}
