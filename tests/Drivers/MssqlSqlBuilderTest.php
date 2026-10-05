<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionBy;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class MssqlSqlBuilderTest
{
    public function testBuildSelectWithTopClause(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->limit(10)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT TOP 10 * FROM [users]');
    }

    public function testBuildSelectWithTopWithTies(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->orderBy(ConditionBy::orderBy()->asc('name'))
            ->limitWithTies(10)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT TOP 10 WITH TIES * FROM [users] ORDER BY [name] ASC');
    }

    public function testBuildSelectWithOffsetRequiresOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->limit(10, 20)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM [users] ORDER BY (SELECT NULL) OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY');
    }

    public function testBuildSelectWithOffsetAndOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->orderBy(ConditionBy::orderBy()->asc('name'))
            ->limit(10, 20)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM [users] ORDER BY [name] ASC OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY');
    }

    public function testBuildInsertWithMergeDataReturnsMergeStatement(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['email'])
            ->set('name', 'Updated')
        ;

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test'])
            ->insertConflictHandler($conflictBuilder)
        ;

        $sql = $qb->build(true);

        Assert::same(
            $sql,
            'MERGE INTO [users] AS target USING (VALUES (\'test@example.com\', \'Test\')) AS source '
                . '([email], [name]) ON target.[email] = source.[email] WHEN MATCHED THEN UPDATE SET '
                . '[name] = \'Updated\' WHEN NOT MATCHED THEN INSERT ([email], [name]) VALUES '
                . '(source.[email], source.[name]);'
        );
    }

    public function testBuildInsertWithoutMergeDataUsesStandardInsert(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'Test'])
        ;

        $sql = $qb->build(true);

        Assert::same($sql, 'INSERT INTO [users] ([name]) VALUES (\'Test\')');
    }

    public function testBuildProcedureWithoutParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_all_users');

        $sql = $qb->build(true);

        Assert::same($sql, 'EXEC [get_all_users]');
    }

    public function testBuildProcedureWithParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_user_by_id', [1, 'active']);

        $sql = $qb->build(true);

        Assert::same($sql, 'EXEC [get_user_by_id] 1, \'active\'');
    }

    public function testBuildProcedureWithNullParameter(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('test_proc', [null, 'value']);

        $sql = $qb->build(true);

        Assert::same($sql, 'EXEC [test_proc] NULL, \'value\'');
    }

    public function testBuildProcedureWithBooleanParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('test_proc', [true, false]);

        $sql = $qb->build(true);

        Assert::same($sql, 'EXEC [test_proc] 1, 0');
    }

    public function testBuildProcedureWithEmptyProcedureNameThrowsException(): void
    {
        $qb = $this->getQueryBuilder();

        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Empty table name is not allowed');

        $qb->procedure('');
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_MSSQL);

        return $qb;
    }
}
