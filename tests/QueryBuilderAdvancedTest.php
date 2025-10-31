<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Builder\RecursiveCteBuilder;
use QBuilder\Builder\UnionBuilder;
use QBuilder\Condition\ConditionBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class QueryBuilderAdvancedTest extends TestCase
{
    public function testSetDriverChangesDriver(): void
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_POSTGRESQL);

        self::assertSame(QbConsts::DRIVER_POSTGRESQL, $qb->getDriver());
    }

    public function testSetDriverWithInvalidDriverThrowsException(): void
    {
        $qb = new QueryBuilder();

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('Unsupported database driver');

        $qb->setDriver('invalid_driver');
    }

    public function testGetDriverInstanceReturnsDriverInstance(): void
    {
        $qb = new QueryBuilder();
        $driver = $qb->getDriverInstance();

        self::assertInstanceOf(DriverInterface::class, $driver);
    }

    public function testGetDriverInstanceCachesInstance(): void
    {
        $qb = new QueryBuilder();
        $driver1 = $qb->getDriverInstance();
        $driver2 = $qb->getDriverInstance();

        self::assertSame($driver1, $driver2);
    }

    public function testGetQueryIsAliasForBuild(): void
    {
        $qb = new QueryBuilder();
        $sql1 = $qb->select('*')->from('users')->getQuery(true);
        $sql2 = $qb->select('*')->from('users')->build(true);

        self::assertSame($sql1, $sql2);
    }

    public function testShowQueryReturnsEmptyStringForEmptyQuery(): void
    {
        $qb = new QueryBuilder();

        self::assertSame('', $qb->showQuery());
    }

    public function testShowQueryReturnsQueryForNonEmptyQuery(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users');

        $sql = $qb->showQuery();
        self::assertStringContainsString('SELECT', $sql);
        self::assertStringContainsString('users', $sql);
    }

    public function testGetTypeReturnsQueryType(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users');

        self::assertSame('SELECT', $qb->getType());
    }

    public function testGetTypeReturnsEmptyStringForEmptyQuery(): void
    {
        $qb = new QueryBuilder();

        self::assertSame('', $qb->getType());
    }

    public function testGetDistinctEnabledReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        self::assertFalse($qb->getDistinctEnabled());

        $qb->select('*')->distinct()->from('users');
        self::assertTrue($qb->getDistinctEnabled());
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

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('SELECT', $sql);
        self::assertStringContainsString('temp_users', $sql);
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

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('SELECT', $sql);
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

        self::assertStringContainsString('UPDATE', $sql);
        self::assertStringContainsString('SET', $sql);
        self::assertStringContainsString('temp_updates', $sql);
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

        self::assertStringContainsString('DELETE', $sql);
        self::assertStringContainsString('temp_deletions', $sql);
    }

    public function testProcedureWithoutParameters(): void
    {
        $qb = new QueryBuilder();
        $qb->procedure('get_all_users');

        self::assertSame('PROCEDURE', $qb->getType());
        self::assertSame('get_all_users', $qb->getProcedureName());
    }

    public function testProcedureWithParameters(): void
    {
        $qb = new QueryBuilder();
        $qb->procedure('get_user_by_id', [1, 'active']);

        self::assertSame('PROCEDURE', $qb->getType());
        self::assertSame('get_user_by_id', $qb->getProcedureName());
        self::assertSame([1, 'active'], $qb->getProcedureParams());
    }

    public function testUnionReturnsUnionBuilder(): void
    {
        $qb = new QueryBuilder();
        $union = $qb->union();

        self::assertInstanceOf(UnionBuilder::class, $union);
    }

    public function testRecursiveCteReturnsRecursiveCteBuilder(): void
    {
        $qb = new QueryBuilder();
        $cte = $qb->recursiveCte('category_tree');

        self::assertInstanceOf(RecursiveCteBuilder::class, $cte);
    }

    public function testConflictBuilderReturnsConflictBuilderInterface(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        self::assertInstanceOf(ConflictBuilderInterface::class, $builder);
    }

    public function testConflictBuilderThrowsExceptionForUnsupportedDriver(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_CLICKHOUSE);

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('ClickHouse does not support conflict handlers');

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

        self::assertNotEmpty($qb->getInsertConflictData());
    }

    public function testFinalForClickHouse(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_CLICKHOUSE);
        $qb->select('*')->from('sensor_data')->final();

        self::assertTrue($qb->isFromFinal());
    }

    public function testFinalSetsFlagForAllDrivers(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->final();

        self::assertTrue($qb->isFromFinal());
    }

    public function testFullJoin(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = ConditionJoin::create($qb, 'user_id', 'id', 'users');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->fullJoin('users', 'u', $joinCondition)
            ->build(true)
        ;

        self::assertStringContainsString('FULL JOIN', $sql);
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

        self::assertStringContainsString('CROSS JOIN', $sql);
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

        self::assertStringContainsString('LEFT JOIN', $sql);
        self::assertStringContainsString('SELECT', $sql);
        self::assertStringContainsString('order_stats', $sql);
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

        self::assertStringContainsString('INNER JOIN', $sql);
        self::assertStringContainsString('price_stats', $sql);
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

        self::assertStringContainsString('RIGHT JOIN', $sql);
        self::assertStringContainsString('product_stats', $sql);
    }

    public function testFullJoinFromSelect(): void
    {
        $qb = new QueryBuilder();
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

        self::assertStringContainsString('FULL JOIN', $sql);
        self::assertStringContainsString('sales_stats', $sql);
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

        self::assertStringContainsString('CROSS JOIN', $sql);
        self::assertStringContainsString('temp', $sql);
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

        self::assertStringContainsString('HAVING', $sql);
        self::assertStringContainsString('COUNT(*)', $sql);
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

        self::assertStringContainsString('HAVING', $sql);
        self::assertStringContainsString('COUNT(*)', $sql);
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

        self::assertStringContainsString('HAVING', $sql);
        self::assertStringContainsString('COUNT(*)', $sql);
        self::assertStringContainsString('SUM(total)', $sql);
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

        self::assertStringContainsString('SELECT', $sql);
        self::assertStringContainsString('order_stats', $sql);
        self::assertStringContainsString('orders', $sql);
    }

    public function testSubQueryCreatesNewQueryBuilderInstance(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery();

        self::assertInstanceOf(QueryBuilder::class, $subquery);
        self::assertNotSame($qb, $subquery);
    }

    public function testSubQueryInheritsDriver(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $subquery = $qb->subQuery();

        self::assertSame(QbConsts::DRIVER_POSTGRESQL, $subquery->getDriver());
    }

    public function testGetSelectFieldsReturnsSelectFields(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id', 'name', Field::set('email', '', 'user_email'));

        $fields = $qb->getSelectFields();
        self::assertCount(3, $fields);
    }

    public function testGetFromTableReturnsTableName(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users');

        self::assertSame('users', $qb->getFromTable());
    }

    public function testGetFromAliasReturnsAlias(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users', 'u');

        self::assertSame('u', $qb->getFromAlias());
    }

    public function testIsFromSubqueryReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()->select('*')->from('temp');
        $qb->select('*')->from($subquery, 't');

        self::assertTrue($qb->isFromSubquery());
    }

    public function testGetJoinClausesReturnsJoinClauses(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = ConditionJoin::create($qb, 'user_id', 'id', 'users');
        $qb->select('*')
            ->from('orders')
            ->leftJoin('users', 'u', $joinCondition)
        ;

        $joins = $qb->getJoinClauses();
        self::assertCount(1, $joins);
    }

    public function testGetWhereBuilderReturnsConditionBuilder(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->where()->eq('status', 'active')->end();

        $whereBuilder = $qb->getWhereBuilder();
        self::assertInstanceOf(ConditionBuilder::class, $whereBuilder);
    }

    public function testGetWhereBuilderReturnsNullWhenNoWhereConditions(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users');

        self::assertNull($qb->getWhereBuilder());
    }

    public function testGetGroupByReturnsGroupByFields(): void
    {
        $qb = new QueryBuilder();
        $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('status'))
        ;

        $groupBy = $qb->getGroupBy();
        self::assertCount(1, $groupBy);
    }

    public function testGetHavingBuilderReturnsConditionBuilder(): void
    {
        $qb = new QueryBuilder();
        $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('orders')
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having()
            ->gt(Field::set('COUNT(*)'), 5)
            ->end()
        ;

        $havingBuilder = $qb->getHavingBuilder();
        self::assertInstanceOf(ConditionBuilder::class, $havingBuilder);
    }

    public function testGetOrderByReturnsOrderByFields(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')
            ->from('users')
            ->orderBy(ConditionBy::orderBy()->asc('name')->desc('created_at'))
        ;

        $orderBy = $qb->getOrderBy();
        self::assertCount(2, $orderBy);
    }

    public function testGetLimitValueReturnsLimitValue(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->limit(10);

        self::assertSame(10, $qb->getLimitValue());
    }

    public function testGetOffsetValueReturnsOffsetValue(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->limit(10, 20);

        self::assertSame(20, $qb->getOffsetValue());
    }

    public function testIsLimitWithTiesReturnsCorrectValue(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $qb->select('*')
            ->from('users')
            ->orderBy(ConditionBy::orderBy()->asc('name'))
            ->limitWithTies(10)
        ;

        self::assertTrue($qb->isLimitWithTies());
    }

    public function testGetInsertRowsReturnsInsertRows(): void
    {
        $qb = new QueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'John'])
            ->insertRow(['name' => 'Jane'])
        ;

        $rows = $qb->getInsertRows();
        self::assertCount(2, $rows);
    }

    public function testGetInsertFieldsReturnsInsertFields(): void
    {
        $qb = new QueryBuilder();
        $qb->insert('users')->insertRow(['name' => 'John', 'age' => 30]);

        $fields = $qb->getInsertFields();
        self::assertCount(2, $fields);
        self::assertContains('name', $fields);
        self::assertContains('age', $fields);
    }

    public function testGetUpdateDataReturnsUpdateData(): void
    {
        $qb = new QueryBuilder();
        $qb->update('users')->updateRow(['status' => 'active', 'name' => 'John']);

        $data = $qb->getUpdateData();
        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('name', $data);
    }

    public function testLimitWithZeroRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->limit(10)->limit(0);

        self::assertNull($qb->getLimitValue());
        self::assertNull($qb->getOffsetValue());
    }

    public function testLimitWithNegativeRemovesLimit(): void
    {
        $qb = new QueryBuilder();
        $qb->select('*')->from('users')->limit(10)->limit(-1);

        self::assertNull($qb->getLimitValue());
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

        self::assertNull($qb->getLimitValue());
        self::assertFalse($qb->isLimitWithTies());
    }
}
