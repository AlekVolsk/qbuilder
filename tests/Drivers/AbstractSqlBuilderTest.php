<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class AbstractSqlBuilderTest extends TestCase
{
    public function testBuildInsertFromSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('name', 'email')
            ->from('temp_users')
            ->where()
            ->eq('verified', 1)
            ->end()
        ;

        $qb->insert('users')
            ->insertFrom($subquery, ['name', 'email'])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('SELECT', $sql);
        self::assertStringContainsString('temp_users', $sql);
    }

    public function testBuildUpdateWithSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_updates')
            ->where()
            ->eq('status', 1)
            ->end()
        ;

        $qb->updateFromSelect('users', $subquery, ['status' => 'verified'], 'id');

        $sql = $qb->build(true);

        self::assertStringContainsString('UPDATE', $sql);
        self::assertStringContainsString('SET', $sql);
        self::assertStringContainsString('WHERE', $sql);
        self::assertStringContainsString('IN', $sql);
        self::assertStringContainsString('temp_updates', $sql);
    }

    public function testBuildDeleteWithSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_deletions')
            ->where()
            ->eq('status', 'deleted')
            ->end()
        ;

        $qb->deleteFromSelect('users', $subquery, 'id');

        $sql = $qb->build(true);

        self::assertStringContainsString('DELETE', $sql);
        self::assertStringContainsString('WHERE', $sql);
        self::assertStringContainsString('IN', $sql);
        self::assertStringContainsString('temp_deletions', $sql);
    }

    public function testBuildUpdateWithSubqueryWithCustomIdField(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_updates')
        ;

        $qb->updateFromSelect('users', $subquery, ['status' => 'verified'], 'user_id');

        $sql = $qb->build(true);

        self::assertStringContainsString('`user_id`', $sql);
        self::assertStringContainsString('IN', $sql);
    }

    public function testBuildDeleteWithSubqueryWithCustomIdField(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('order_id')
            ->from('temp_deletions')
        ;

        $qb->deleteFromSelect('orders', $subquery, 'order_id');

        $sql = $qb->build(true);

        self::assertStringContainsString('`order_id`', $sql);
        self::assertStringContainsString('IN', $sql);
    }

    public function testGetAliasKeywordReturnsAsKeyword(): void
    {
        $qb = $this->getQueryBuilder();
        $sqlBuilder = $qb->getDriverInstance()->getSqlBuilder($qb);

        $reflection = new \ReflectionClass($sqlBuilder);
        $method = $reflection->getMethod('getAliasKeyword');
        $method->setAccessible(true);
        $result = $method->invoke($sqlBuilder);

        self::assertSame(' AS ', $result);
    }

    public function testBuildUpdateSetsFormatsUpdateDataCorrectly(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->update('users')
            ->updateRow(['name' => 'John', 'age' => 30, 'active' => true])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('SET', $sql);
        self::assertStringContainsString('`name`', $sql);
        self::assertStringContainsString('`age`', $sql);
        self::assertStringContainsString('`active`', $sql);
    }

    public function testBuildUpdateSetsWithNullValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->update('users')
            ->updateRow(['name' => null, 'age' => 30])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('`name` = NULL', $sql);
        self::assertStringContainsString('`age` = 30', $sql);
    }

    public function testBuildUpdateSetsWithBooleanValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->update('users')
            ->updateRow(['active' => true, 'verified' => false])
        ;

        $sql = $qb->build(true);

        self::assertSame('UPDATE `users` SET `active` = 1, `verified` = 0', $sql);
    }

    public function testBuildInsertRowsWithMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('VALUES', $sql);
        self::assertStringContainsString('John', $sql);
        self::assertStringContainsString('Jane', $sql);
    }

    public function testBuildInsertRowsWithNullValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => null, 'email' => 'john@example.com'])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('NULL', $sql);
        self::assertStringContainsString('John', $sql);
    }

    public function testBuildInsertRowsWithNumericValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('products')
            ->insertRow(['name' => 'Product', 'price' => 99.99, 'quantity' => 10])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('99.99', $sql);
        self::assertStringContainsString('10', $sql);
    }

    public function testBuildInsertWithEmptyDataReturnsBaseSql(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users');

        $sql = $qb->build(true);

        self::assertSame('INSERT INTO `users`', $sql);
    }

    public function testBuildUpdateWithEmptyUpdateDataReturnsBaseSql(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->update('users');

        $sql = $qb->build(true);

        self::assertSame('UPDATE `users`', $sql);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_PDO_MYSQL);

        return $qb;
    }
}
