<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Drivers\Mssql\MssqlMergeBuilder;
use QBuilder\Drivers\Oracle\OracleMergeBuilder;
use QBuilder\Exceptions\MissingRequirementException;
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
final class MergeBuilderTest
{
    public function testMssqlConflictBuilderIsMerge(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        Assert::instanceOf($builder, MssqlMergeBuilder::class);
    }

    public function testOracleConflictBuilderIsMerge(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $builder = $qb->conflictBuilder();

        Assert::instanceOf($builder, OracleMergeBuilder::class);
    }

    #[DataProvider('provideMergeDrivers')]
    public function testBuildReturnsEmptyStringWhenUpdatesAreEmpty(string $driver): void
    {
        $qb = new QueryBuilder($driver);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $builder->conflictTarget(['email']);

        Assert::same($builder->build(), '');
    }

    #[DataProvider('provideMergeDrivers')]
    public function testMergeWithoutConflictTargetIsRejected(string $driver): void
    {
        $qb = new QueryBuilder($driver);
        $builder = $qb->conflictBuilder()->set('name', 'Updated');
        $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'Test']);

        Expect::exception(MissingRequirementException::class)->withMessageContaining('MERGE needs a conflict target');

        $builder->build();
    }

    #[DataProvider('provideMergeDrivers')]
    public function testMergeBeforeInsertRowsIsRejected(string $driver): void
    {
        $qb = new QueryBuilder($driver);
        $builder = $qb->conflictBuilder()->conflictTarget(['email'])->set('name', 'Updated');
        $qb->insert('users');

        Expect::exception(MissingRequirementException::class)
            ->withMessageContaining('call insertRow() before insertConflictHandler()')
        ;

        $qb->insertConflictHandler($builder);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMergeDrivers(): iterable
    {
        yield 'MS SQL Server' => [QbConsts::DRIVER_MSSQL];
        yield 'Oracle' => [QbConsts::DRIVER_ORACLE];
    }

    public function testMssqlMergeBuilderBuildGeneratesCorrectMergeStatement(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test', 'age' => 30])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('age', 1)
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 'Test', 30)) AS source ([email], [name], [age])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [name] = 'Updated', [age] = target.[age] + 1\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [name], [age])\n"
                . '  VALUES (source.[email], source.[name], source.[age]);'
        );
    }

    public function testOracleMergeBuilderBuildGeneratesCorrectMergeStatementForSingleRow(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO \"USERS\" target\n"
                . 'USING (SELECT \'test@example.com\' AS "EMAIL", \'Test\' AS "NAME" FROM DUAL) source ON '
                . "(target.\"EMAIL\" = source.\"EMAIL\")\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET \"NAME\" = 'Updated'\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT (\"EMAIL\", \"NAME\")\n"
                . '  VALUES (source."EMAIL", source."NAME")'
        );
    }

    public function testOracleMergeBuilderBuildGeneratesCorrectMergeStatementForMultipleRows(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test1@example.com', 'name' => 'Test1'])
            ->insertRow(['email' => 'test2@example.com', 'name' => 'Test2'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO \"USERS\" target\n"
                . "USING (SELECT 'test1@example.com' AS \"EMAIL\", 'Test1' AS \"NAME\" FROM DUAL\n"
                . 'UNION ALL SELECT \'test2@example.com\' AS "EMAIL", \'Test2\' AS "NAME" FROM DUAL) source ON '
                . "(target.\"EMAIL\" = source.\"EMAIL\")\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET \"NAME\" = 'Updated'\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT (\"EMAIL\", \"NAME\")\n"
                . '  VALUES (source."EMAIL", source."NAME")'
        );
    }

    public function testExcludedMethodInMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 'Test')) AS source ([email], [name])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [name] = source.[name]\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [name])\n"
                . '  VALUES (source.[email], source.[name]);'
        );
    }

    public function testExcludedMethodWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'user_name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name', 'user_name')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 'Test')) AS source ([email], [user_name])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [name] = source.[user_name]\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [user_name])\n"
                . '  VALUES (source.[email], source.[user_name]);'
        );
    }

    public function testSetWithNullValue(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', null)
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 'Test')) AS source ([email], [name])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [name] = NULL\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [name])\n"
                . '  VALUES (source.[email], source.[name]);'
        );
    }

    public function testSetWithBooleanValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'active' => true])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('active', true)
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 1)) AS source ([email], [active])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [active] = 1\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [active])\n"
                . '  VALUES (source.[email], source.[active]);'
        );

        $qb2 = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder2 = $qb2->conflictBuilder();
        $qb2->insert('users')
            ->insertRow(['email' => 'test2@example.com', 'active' => false])
        ;

        $sql2 = $builder2->conflictTarget(['email'])
            ->set('active', false)
            ->build()
        ;

        Assert::same(
            $sql2,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test2@example.com', 0)) AS source ([email], [active])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [active] = 0\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [active])\n"
                . '  VALUES (source.[email], source.[active]);'
        );
    }

    public function testSetWithNumericValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'age' => 30])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('age', 25)
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 30)) AS source ([email], [age])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [age] = 25\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [age])\n"
                . '  VALUES (source.[email], source.[age]);'
        );
    }

    public function testIncrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'views' => 10])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->increment('views', 1)
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 10)) AS source ([email], [views])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [views] = target.[views] + 1\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [views])\n"
                . '  VALUES (source.[email], source.[views]);'
        );
    }

    public function testDecrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'balance' => 100])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->decrement('balance', 10)
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 100)) AS source ([email], [balance])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [balance] = target.[balance] - 10\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [balance])\n"
                . '  VALUES (source.[email], source.[balance]);'
        );
    }

    public function testSetNullMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->setNull('name')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 'Test')) AS source ([email], [name])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [name] = NULL\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [name])\n"
                . '  VALUES (source.[email], source.[name]);'
        );
    }

    public function testSqlFunctionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->sqlFunction('updated_at', 'GETDATE()')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 'Test')) AS source ([email], [name])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [updated_at] = GETDATE()\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [name])\n"
                . '  VALUES (source.[email], source.[name]);'
        );
    }

    public function testConflictTargetWithMultipleFields(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('orders')
            ->insertRow(['user_id' => 1, 'product_id' => 2, 'quantity' => 5])
        ;

        $sql = $builder->conflictTarget(['user_id', 'product_id'])
            ->set('quantity', 10)
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [orders] AS target\n"
                . "USING (VALUES (1, 2, 5)) AS source ([user_id], [product_id], [quantity])\n"
                . "ON target.[user_id] = source.[user_id] AND target.[product_id] = source.[product_id]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [quantity] = 10\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([user_id], [product_id], [quantity])\n"
                . '  VALUES (source.[user_id], source.[product_id], source.[quantity]);'
        );
    }

    public function testMultipleUpdateOperations(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test', 'views' => 0])
        ;

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('views', 1)
            ->sqlFunction('updated_at', 'GETDATE()')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . "USING (VALUES ('test@example.com', 'Test', 0)) AS source ([email], [name], [views])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [name] = 'Updated', [views] = target.[views] + 1, [updated_at] = GETDATE()\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [name], [views])\n"
                . '  VALUES (source.[email], source.[name], source.[views]);'
        );
    }

    public function testExpressionMethodInMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

        $qb->insert('products')
            ->insertRow(['id' => 1, 'price' => 100, 'discount' => 10])
        ;

        $sql = $builder->conflictTarget(['id'])
            ->expression('price', 'price * 1.1')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [products] AS target\n"
                . "USING (VALUES (1, 100, 10)) AS source ([id], [price], [discount])\n"
                . "ON target.[id] = source.[id]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [price] = price * 1.1\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([id], [price], [discount])\n"
                . '  VALUES (source.[id], source.[price], source.[discount]);'
        );
    }

    public function testCaseExpressionMethodInMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $builder = $qb->conflictBuilder();

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

        Assert::same(
            $sql,
            "MERGE INTO [users] AS target\n"
                . 'USING (VALUES (\'test@example.com\', \'pending\', 5)) AS source ([email], [status], '
                . "[count])\n"
                . "ON target.[email] = source.[email]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [status] = CASE WHEN count > 10 THEN 'active' ELSE 'pending' END\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([email], [status], [count])\n"
                . '  VALUES (source.[email], source.[status], source.[count]);'
        );
    }

    public function testExpressionMethodInOracleMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $builder = $qb->conflictBuilder();

        $qb->insert('products')
            ->insertRow(['id' => 1, 'price' => 100])
        ;

        $sql = $builder->conflictTarget(['id'])
            ->expression('price', 'price * 1.1')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO \"PRODUCTS\" target\n"
                . 'USING (SELECT 1 AS "ID", 100 AS "PRICE" FROM DUAL) source ON (target."ID" = '
                . "source.\"ID\")\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET \"PRICE\" = price * 1.1\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT (\"ID\", \"PRICE\")\n"
                . '  VALUES (source."ID", source."PRICE")'
        );
    }

    public function testCaseExpressionMethodInOracleMergeBuilder(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $builder = $qb->conflictBuilder();

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

        Assert::same(
            $sql,
            "MERGE INTO \"USERS\" target\n"
                . 'USING (SELECT \'test@example.com\' AS "EMAIL", \'pending\' AS "STATUS" FROM DUAL) source ON '
                . "(target.\"EMAIL\" = source.\"EMAIL\")\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET \"STATUS\" = CASE WHEN count > 10 THEN 'active' ELSE 'pending' END\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT (\"EMAIL\", \"STATUS\")\n"
                . '  VALUES (source."EMAIL", source."STATUS")'
        );
    }
}
