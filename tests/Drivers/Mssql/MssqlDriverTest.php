<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class MssqlDriverTest
{
    public function testMssqlQuoting(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        Assert::same($sql, 'SELECT [id], [name] FROM [users]');
    }

    public function testMssqlTopLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10)->build(true);

        Assert::same($sql, 'SELECT TOP 10 * FROM [users]');
    }

    public function testMssqlTopWithTies(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('name', 'salary')
            ->from('employees')
            ->orderBy(ConditionBy::orderBy()->add('salary', '', 'DESC'))
            ->limitWithTies(5)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT TOP 5 WITH TIES [name], [salary] FROM [employees] '
        . 'ORDER BY [salary] DESC');
    }

    public function testMssqlOffsetFetch(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->orderBy(ConditionBy::orderBy()->add('id'))
            ->limit(10, 20)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM [users] ORDER BY [id] ASC OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY');
    }

    public function testMssqlProcedureNoParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_all_users')->build(true);

        Assert::same($sql, 'EXEC [get_all_users]');
    }

    public function testMssqlProcedureWithParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_user_by_id', [1, 'active'])->build(true);

        Assert::same($sql, "EXEC [get_user_by_id] 1, 'active'");
    }

    public function testMssqlMergeUpsert(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['id'])
            ->set('name', 'John Updated')
            ->set('age', 31)
        ;

        $sql = $qb->insert('users')->insertRow(['id' => 1, 'name' => 'John', 'age' => 30])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'MERGE INTO [users] AS target '
            . 'USING (VALUES (1, \'John\', 30)) AS source ([id], [name], [age]) '
            . 'ON target.[id] = source.[id] '
            . 'WHEN MATCHED THEN UPDATE SET [name] = \'John Updated\', [age] = 31 '
            . 'WHEN NOT MATCHED THEN INSERT ([id], [name], [age]) VALUES (source.[id], source.[name], source.[age]);';

        Assert::same($sql, $expected);
    }

    public function testMssqlInsertSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')->insertRow(['name' => 'John', 'age' => 30])->build(true);

        Assert::same($sql, "INSERT INTO [users] ([name], [age]) VALUES ('John', 30)");
    }

    public function testMssqlInsertMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
            ->build(true)
        ;

        $expected = "INSERT INTO [users] ([name], [age]) VALUES ('John', 30), ('Jane', 25)";

        Assert::same($sql, $expected);
    }

    public function testMssqlUpdateSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->update('users')->updateRow(['status' => 'inactive'])
            ->where()->eq('id', 1)->end()
            ->build(true)
        ;

        Assert::same($sql, 'UPDATE [users] SET [status] = \'inactive\' WHERE ([id] = 1)');
    }

    public function testMssqlDeleteSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->delete('users')->where()->eq('id', 1)->end()->build(true);

        Assert::same($sql, 'DELETE FROM [users] WHERE ([id] = 1)');
    }

    public function testMssqlEscapeLikePattern(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM [users] WHERE ([name] LIKE '50[%]%')");
    }

    public function testMssqlComplexJoin(): void
    {
        $qb = $this->getQueryBuilder();

        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'user_id', 'u', 'id');

        $orderBy = ConditionBy::orderBy()->desc('total', 'o');

        $sql = $qb->select(
            Field::set('id', 'u'),
            Field::set('name', 'u'),
            Field::set('total', 'o')
        )
            ->from('users', 'u')
            ->leftJoin('orders', 'o', $joinCondition)
            ->where()->gte(Field::set('total', 'o'), 100)->end()
            ->orderBy($orderBy)
            ->limit(20)
            ->build(true)
        ;

        $expected = 'SELECT TOP 20 [u].[id], [u].[name], [o].[total] '
            . 'FROM [users] AS [u] '
            . 'LEFT JOIN [orders] AS [o] ON ([o].[user_id] = [u].[id]) '
            . 'WHERE ([o].[total] >= 100) '
            . 'ORDER BY [o].[total] DESC';

        Assert::same($sql, $expected);
    }

    public function testMssqlSubqueryInSelect(): void
    {
        $qb = $this->getQueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('COUNT(*)'))
            ->from('orders')
            ->where()->eqField('orders', 'user_id', 'users', 'id')->end()
        ;

        $sql = $qb->select('id', 'name', Field::subquery($subquery, 'order_count'))
            ->from('users')
            ->build(true)
        ;

        $expected = 'SELECT [id], [name], '
            . '(SELECT COUNT(*) FROM [orders] WHERE ([orders].[user_id] = [users].[id])) '
            . 'AS [order_count] '
            . 'FROM [users]';

        Assert::same($sql, $expected);
    }

    public function testMssqlWhereExists(): void
    {
        $qb = $this->getQueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('1'))
            ->from('orders')
            ->where()
            ->eqField('orders', 'user_id', 'users', 'id')
            ->and()->gte('total', 1000)
            ->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->exists($subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM [users] WHERE (EXISTS '
            . '(SELECT 1 FROM [orders] WHERE ([orders].[user_id] = [users].[id]) '
            . 'AND ([total] >= 1000)))';

        Assert::same($sql, $expected);
    }

    public function testMssqlGroupByHaving(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having()->gt(Field::set('COUNT(*)', '', 'total'), 5)->end()
            ->build(true)
        ;

        $expected = 'SELECT [status], COUNT(*) AS [total] FROM [users] '
            . 'GROUP BY [status] HAVING (COUNT(*) > 5)';

        Assert::same($sql, $expected);
    }

    public function testMssqlDistinct(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->build(true);

        Assert::same($sql, 'SELECT DISTINCT [user_id] FROM [orders]');
    }

    public function testMssqlDistinctMultipleFields(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id', 'status')->distinct()->from('orders')->build(true);

        Assert::same($sql, 'SELECT DISTINCT [user_id], [status] FROM [orders]');
    }

    public function testMssqlDistinctWithTop(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->limit(10)->build(true);

        Assert::same($sql, 'SELECT DISTINCT TOP 10 [user_id] FROM [orders]');
    }

    public function testMssqlDistinctWithWhere(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')
            ->where()->eq('status', 'completed')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT DISTINCT [user_id] FROM [orders] WHERE ([status] = \'completed\')');
    }

    public function testMssqlDistinctWithOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('category');
        $sql = $qb->select('category')->distinct()->from('products')
            ->orderBy($orderBy)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT DISTINCT [category] FROM [products] ORDER BY [category] ASC');
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_MSSQL);

        return $qb;
    }
}
