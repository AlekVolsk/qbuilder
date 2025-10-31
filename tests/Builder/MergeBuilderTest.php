<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Drivers\Mssql\MssqlMergeBuilder;
use QBuilder\Drivers\Oracle\OracleMergeBuilder;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class MergeBuilderTest extends TestCase
{
    public function testMssqlMergeBuilderCreateReturnsInstance(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        self::assertInstanceOf(MssqlMergeBuilder::class, $builder);
    }

    public function testOracleMergeBuilderCreateReturnsInstance(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $driver = $qb->getDriverInstance();
        $builder = OracleMergeBuilder::create($qb, $driver);

        self::assertInstanceOf(OracleMergeBuilder::class, $builder);
    }

    public function testBuildReturnsEmptyStringWhenUpdatesAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $builder->conflictTarget(['email']);

        self::assertSame('', $builder->build());
    }

    public function testBuildReturnsEmptyStringWhenConflictFieldsAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $builder->set('name', 'Updated');

        self::assertSame('', $builder->build());
    }

    public function testBuildReturnsEmptyStringWhenInsertRowsAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users');

        $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
        ;

        self::assertSame('', $builder->build());
    }

    public function testBuildReturnsEmptyStringWhenInsertFieldsAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users');

        $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
        ;

        self::assertSame('', $builder->build());
    }

    public function testMssqlMergeBuilderBuildGeneratesCorrectMergeStatement(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test', 'age' => 30])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('age', 1)
            ->build()
        ;

        self::assertStringContainsString('MERGE INTO', $sql);
        self::assertStringContainsString('USING (VALUES', $sql);
        self::assertStringContainsString('WHEN MATCHED THEN', $sql);
        self::assertStringContainsString('UPDATE SET', $sql);
        self::assertStringContainsString('WHEN NOT MATCHED THEN', $sql);
        self::assertStringContainsString('INSERT', $sql);
        self::assertStringContainsString('VALUES', $sql);
    }

    public function testOracleMergeBuilderBuildGeneratesCorrectMergeStatementForSingleRow(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $driver = $qb->getDriverInstance();
        $builder = OracleMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->build()
        ;

        self::assertStringContainsString('MERGE INTO', $sql);
        self::assertStringContainsString('USING (', $sql);
        self::assertStringContainsString('SELECT', $sql);
        self::assertStringContainsString('FROM DUAL', $sql);
        self::assertStringContainsString('WHEN MATCHED THEN', $sql);
        self::assertStringContainsString('UPDATE SET', $sql);
        self::assertStringContainsString('WHEN NOT MATCHED THEN', $sql);
        self::assertStringContainsString('INSERT', $sql);
    }

    public function testOracleMergeBuilderBuildGeneratesCorrectMergeStatementForMultipleRows(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $driver = $qb->getDriverInstance();
        $builder = OracleMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test1@example.com', 'name' => 'Test1'])
            ->insertRow(['email' => 'test2@example.com', 'name' => 'Test2'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->build()
        ;

        self::assertStringContainsString('MERGE INTO', $sql);
        self::assertStringContainsString('UNION ALL SELECT', $sql);
        self::assertStringContainsString('FROM DUAL', $sql);
    }

    public function testExcludedMethodInMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name')
            ->build()
        ;

        self::assertStringContainsString('source.[name]', $sql);
    }

    public function testExcludedMethodWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'user_name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name', 'user_name')
            ->build()
        ;

        self::assertStringContainsString('source.[user_name]', $sql);
    }

    public function testSetWithNullValue(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', null)
            ->build()
        ;

        self::assertStringContainsString('[name] = NULL', $sql);
    }

    public function testSetWithBooleanValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'active' => true])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('active', true)
            ->build()
        ;

        self::assertStringContainsString('[active] = 1', $sql);

        $qb2 = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver2 = $qb2->getDriverInstance();
        $builder2 = MssqlMergeBuilder::create($qb2, $driver2);
        $qb2->insert('users')
            ->insertRow(['email' => 'test2@example.com', 'active' => false])
        ;

        $sql2 = $builder2->conflictTarget(['email'])
            ->set('active', false)
            ->build()
        ;

        self::assertStringContainsString('[active] = 0', $sql2);
    }

    public function testSetWithNumericValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'age' => 30])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('age', 25)
            ->build()
        ;

        self::assertStringContainsString('[age] = 25', $sql);
    }

    public function testIncrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'views' => 10])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->increment('views', 1)
            ->build()
        ;

        self::assertStringContainsString('[views] = [views] + 1', $sql);
    }

    public function testDecrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'balance' => 100])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->decrement('balance', 10)
            ->build()
        ;

        self::assertStringContainsString('[balance] = [balance] - 10', $sql);
    }

    public function testSetNullMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->setNull('name')
            ->build()
        ;

        self::assertStringContainsString('[name] = NULL', $sql);
    }

    public function testSqlFunctionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->sqlFunction('updated_at', 'GETDATE()')
            ->build()
        ;

        self::assertStringContainsString('[updated_at] = GETDATE()', $sql);
    }

    public function testConflictTargetWithMultipleFields(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('orders')
            ->insertRow(['user_id' => 1, 'product_id' => 2, 'quantity' => 5])
        ;

        $sql = $builder->conflictTarget(['user_id', 'product_id'])
            ->set('quantity', 10)
            ->build()
        ;

        self::assertStringContainsString('target.[user_id] = source.[user_id]', $sql);
        self::assertStringContainsString('target.[product_id] = source.[product_id]', $sql);
        self::assertStringContainsString('AND', $sql);
    }

    public function testMultipleUpdateOperations(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test', 'views' => 0])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('views', 1)
            ->sqlFunction('updated_at', 'GETDATE()')
            ->build()
        ;

        self::assertStringContainsString('[name] = \'Updated\'', $sql);
        self::assertStringContainsString('[views] = [views] + 1', $sql);
        self::assertStringContainsString('[updated_at] = GETDATE()', $sql);
    }

    public function testExcludedMethodFromAbstractMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test', 'age' => 30])
        ;

        $builder->conflictTarget(['email'])
            ->excluded('name')
            ->excluded('age', 'age')
        ;

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('excluded');
        self::assertTrue($method->isPublic());
        self::assertSame('QBuilder\Builder\AbstractMergeBuilder', $method->getDeclaringClass()->getName());

        $sql = $builder->build();
        self::assertStringContainsString('source.[name]', $sql);
        self::assertStringContainsString('source.[age]', $sql);
    }

    public function testExpressionMethodInMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('products')
            ->insertRow(['id' => 1, 'price' => 100, 'discount' => 10])
        ;

        $sql = $builder->conflictTarget(['id'])
            ->expression('price', 'price * 1.1')
            ->build()
        ;

        self::assertStringContainsString('[price] = price * 1.1', $sql);
    }

    public function testCaseExpressionMethodInMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $driver = $qb->getDriverInstance();
        $builder = MssqlMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'status' => 'pending', 'count' => 5])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->caseExpression('status', [
                'WHEN count > 10 THEN \'active\'',
                'ELSE \'pending\'',
            ])
            ->build()
        ;

        self::assertStringContainsString('CASE', $sql);
        self::assertStringContainsString('WHEN count > 10 THEN \'active\'', $sql);
        self::assertStringContainsString('ELSE \'pending\'', $sql);
        self::assertStringContainsString('END', $sql);
    }

    public function testExpressionMethodInOracleMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $driver = $qb->getDriverInstance();
        $builder = OracleMergeBuilder::create($qb, $driver);

        $qb->insert('products')
            ->insertRow(['id' => 1, 'price' => 100])
        ;

        $sql = $builder->conflictTarget(['id'])
            ->expression('price', 'price * 1.1')
            ->build()
        ;

        self::assertStringContainsString('"PRICE" = price * 1.1', $sql);
    }

    public function testCaseExpressionMethodInOracleMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $driver = $qb->getDriverInstance();
        $builder = OracleMergeBuilder::create($qb, $driver);

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'status' => 'pending'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->caseExpression('status', [
                'WHEN count > 10 THEN \'active\'',
                'ELSE \'pending\'',
            ])
            ->build()
        ;

        self::assertStringContainsString('CASE', $sql);
        self::assertStringContainsString('WHEN count > 10 THEN \'active\'', $sql);
        self::assertStringContainsString('ELSE \'pending\'', $sql);
    }
}
