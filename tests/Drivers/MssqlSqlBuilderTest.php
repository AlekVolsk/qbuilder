<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class MssqlSqlBuilderTest extends TestCase
{
    public function testBuildSelectWithTopClause(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->limit(10)
            ->build(true)
        ;

        self::assertStringContainsString('TOP 10', $sql);
        self::assertStringNotContainsString('LIMIT', $sql);
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

        self::assertStringContainsString('TOP 10 WITH TIES', $sql);
    }

    public function testBuildSelectWithOffsetRequiresOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->limit(10, 20)
            ->build(true)
        ;

        self::assertStringContainsString('ORDER BY (SELECT NULL)', $sql);
        self::assertStringContainsString('OFFSET', $sql);
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

        self::assertStringContainsString('ORDER BY', $sql);
        self::assertStringContainsString('OFFSET', $sql);
        self::assertStringNotContainsString('ORDER BY (SELECT NULL)', $sql);
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

        self::assertStringContainsString('MERGE INTO', $sql);
        self::assertStringNotContainsString('INSERT INTO', $sql);
    }

    public function testBuildInsertWithoutMergeDataUsesStandardInsert(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'Test'])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringNotContainsString('MERGE INTO', $sql);
    }

    public function testBuildProcedureWithoutParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_all_users');

        $sql = $qb->build(true);

        self::assertStringContainsString('EXEC', $sql);
        self::assertStringContainsString('get_all_users', $sql);
        self::assertStringNotContainsString('()', $sql);
    }

    public function testBuildProcedureWithParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_user_by_id', [1, 'active']);

        $sql = $qb->build(true);

        self::assertStringContainsString('EXEC', $sql);
        self::assertStringContainsString('get_user_by_id', $sql);
        self::assertStringContainsString('1', $sql);
        self::assertStringContainsString('active', $sql);
    }

    public function testBuildProcedureWithNullParameter(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('test_proc', [null, 'value']);

        $sql = $qb->build(true);

        self::assertStringContainsString('NULL', $sql);
        self::assertStringContainsString('value', $sql);
    }

    public function testBuildProcedureWithBooleanParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('test_proc', [true, false]);

        $sql = $qb->build(true);

        self::assertStringContainsString('1', $sql);
        self::assertStringContainsString('0', $sql);
    }

    public function testBuildProcedureWithEmptyProcedureNameThrowsException(): void
    {
        $qb = $this->getQueryBuilder();

        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Empty table name is not allowed');

        $qb->procedure('');
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_MSSQL);

        return $qb;
    }
}
