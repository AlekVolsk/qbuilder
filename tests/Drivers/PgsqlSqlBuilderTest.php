<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class PgsqlSqlBuilderTest extends TestCase
{
    public function testBuildInsertWithInsertRows(): void
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

    public function testBuildInsertWithInsertFromSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('name', 'age')
            ->from('temp_users')
            ->where()
            ->eq('verified', 1)
            ->end()
        ;

        $qb->insert('users')
            ->insertFrom($subquery, ['name', 'age'])
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('SELECT', $sql);
        self::assertStringContainsString('temp_users', $sql);
    }

    public function testBuildInsertWithInsertFromSubqueryWithoutFields(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('name', 'age')
            ->from('temp_users')
        ;

        $qb->insert('users')
            ->insertFrom($subquery)
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('SELECT', $sql);
    }

    public function testBuildInsertWithOnConflictHandler(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('views', 1)
        ;

        $qb->insert('users')
            ->insertRow(['email' => 'test@example.com', 'name' => 'Test', 'views' => 0])
            ->insertConflictHandler($conflictBuilder)
        ;

        $sql = $qb->build(true);

        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('ON CONFLICT', $sql);
        self::assertStringContainsString('DO UPDATE SET', $sql);
    }

    public function testBuildProcedureWithoutParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_all_users');

        $sql = $qb->build(true);

        self::assertStringContainsString('CALL', $sql);
        self::assertStringContainsString('get_all_users', $sql);
        self::assertStringContainsString('()', $sql);
    }

    public function testBuildProcedureWithParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_user_by_id', [1, 'active']);

        $sql = $qb->build(true);

        self::assertStringContainsString('CALL', $sql);
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

        self::assertStringContainsString('TRUE', $sql);
        self::assertStringContainsString('FALSE', $sql);
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
        $qb->setDriver(QbConsts::DRIVER_POSTGRESQL);

        return $qb;
    }
}
