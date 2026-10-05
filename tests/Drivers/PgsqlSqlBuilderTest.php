<?php

declare(strict_types=1);

namespace QBuilder\Tests;

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
final class PgsqlSqlBuilderTest
{
    public function testBuildInsertWithInsertRows(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
        ;

        $sql = $qb->build(true);

        Assert::same($sql, 'INSERT INTO "users" ("name", "age") VALUES (\'John\', 30), (\'Jane\', 25)');
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

        Assert::same(
            $sql,
            'INSERT INTO "users" ("name", "age") SELECT "name", "age" FROM "temp_users" WHERE '
                . '("verified" = 1)'
        );
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

        Assert::same($sql, 'INSERT INTO "users" SELECT "name", "age" FROM "temp_users"');
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

        Assert::same(
            $sql,
            'INSERT INTO "users" ("email", "name", "views") VALUES (\'test@example.com\', \'Test\', 0) '
                . 'ON CONFLICT ("email") DO UPDATE SET "name" = \'Updated\', "views" = "users"."views" + 1'
        );
    }

    public function testBuildProcedureWithoutParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_all_users');

        $sql = $qb->build(true);

        Assert::same($sql, 'CALL "get_all_users"()');
    }

    public function testBuildProcedureWithParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('get_user_by_id', [1, 'active']);

        $sql = $qb->build(true);

        Assert::same($sql, 'CALL "get_user_by_id"(1, \'active\')');
    }

    public function testBuildProcedureWithNullParameter(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('test_proc', [null, 'value']);

        $sql = $qb->build(true);

        Assert::same($sql, 'CALL "test_proc"(NULL, \'value\')');
    }

    public function testBuildProcedureWithBooleanParameters(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->procedure('test_proc', [true, false]);

        $sql = $qb->build(true);

        Assert::same($sql, 'CALL "test_proc"(TRUE, FALSE)');
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
        $qb->setDriver(QbConsts::DRIVER_POSTGRESQL);

        return $qb;
    }
}
