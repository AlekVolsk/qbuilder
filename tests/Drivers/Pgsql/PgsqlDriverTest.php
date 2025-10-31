<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class PgsqlDriverTest extends TestCase
{
    public function testPgsqlQuoting(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        self::assertSame('SELECT "id", "name" FROM "users"', $sql);
    }

    public function testPgsqlOnConflict(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['email'])
            ->set('name', 'John Updated')
            ->sqlFunction('updated_at', 'NOW()')
        ;

        $sql = $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'John'])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO "users" ("email", "name") '
            .'VALUES (\'test@example.com\', \'John\') '
            .'ON CONFLICT ("email") DO UPDATE SET '
            .'"name" = \'John Updated\', "updated_at" = NOW()';

        self::assertSame($expected, $sql);
    }

    public function testPgsqlOnConflictDoNothing(): void
    {
        $qb = $this->getQueryBuilder();

        $this->expectException(MissingRequirementException::class);
        $this->expectExceptionMessage('Conflict target must be specified for PostgreSQL ON CONFLICT');

        $conflictBuilder = $qb->conflictBuilder();

        $sql = $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'John'])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;
    }

    public function testPgsqlOnConflictExcluded(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['id'])
            ->excluded('name')
            ->excluded('counter')
        ;

        $sql = $qb->insert('users')->insertRow(['id' => 1, 'name' => 'John', 'counter' => 1])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO "users" ("id", "name", "counter") VALUES (1, \'John\', 1) '
            .'ON CONFLICT ("id") DO UPDATE SET '
            .'"name" = EXCLUDED."name", "counter" = EXCLUDED."counter"';

        self::assertSame($expected, $sql);
    }

    public function testPgsqlOnConflictIncrement(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['id'])
            ->increment('views', 1)
        ;

        $sql = $qb->insert('users')->insertRow(['id' => 1, 'views' => 1])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO "users" ("id", "views") VALUES (1, 1) '
            .'ON CONFLICT ("id") DO UPDATE SET "views" = "views" + 1';

        self::assertSame($expected, $sql);
    }

    public function testPgsqlOnConflictSetEscapesUserInput(): void
    {
        $qb = $this->getQueryBuilder();

        $maliciousInput = "'; DROP TABLE users; --";
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['email'])
            ->set('name', $maliciousInput)
        ;

        $sql = $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'John'])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        self::assertTrue(str_contains($sql, "'''; DROP TABLE users; --'"));

        $functionLike = 'some_value()';
        $conflictBuilder2 = $qb->conflictBuilder()
            ->conflictTarget(['email'])
            ->set('name', $functionLike)
        ;

        $sql2 = $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'John'])
            ->insertConflictHandler($conflictBuilder2)
            ->build(true)
        ;

        self::assertTrue(str_contains($sql2, "'some_value()'"));
    }

    public function testPgsqlLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10)->build(true);

        self::assertSame('SELECT * FROM "users" LIMIT 10', $sql);
    }

    public function testPgsqlLimitOffset(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10, 20)->build(true);

        self::assertSame('SELECT * FROM "users" LIMIT 10 OFFSET 20', $sql);
    }

    public function testPgsqlLimitWithTies(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('name', 'salary')
            ->from('employees')
            ->orderBy(ConditionBy::orderBy()->add('salary', '', 'DESC'))
            ->limitWithTies(5)
            ->build(true)
        ;

        self::assertSame(
            'SELECT "name", "salary" FROM "employees" '
            .'ORDER BY "salary" DESC FETCH FIRST 5 ROWS WITH TIES',
            $sql
        );
    }

    public function testPgsqlProcedureNoParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_all_users')->build(true);

        self::assertSame('CALL "get_all_users"()', $sql);
    }

    public function testPgsqlProcedureWithParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_user_by_id', [1, 'active'])->build(true);

        self::assertSame("CALL \"get_user_by_id\"(1, 'active')", $sql);
    }

    public function testPgsqlInsertMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
            ->build(true)
        ;

        $expected = 'INSERT INTO "users" ("name", "age") VALUES (\'John\', 30), (\'Jane\', 25)';

        self::assertSame($expected, $sql);
    }

    public function testPgsqlEscapeLikePattern(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM "users" WHERE ("name" LIKE \'50\\\%\')', $sql);
    }

    public function testPgsqlComplexJoin(): void
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
            ->innerJoin('orders', 'o', $joinCondition)
            ->where()->gte(Field::set('total', 'o'), 100)->end()
            ->orderBy($orderBy)
            ->limit(10)
            ->build(true)
        ;

        $expected = 'SELECT "u"."id", "u"."name", "o"."total" '
            .'FROM "users" AS "u" '
            .'INNER JOIN "orders" AS "o" ON ("o"."user_id" = "u"."id") '
            .'WHERE ("o"."total" >= 100) '
            .'ORDER BY "o"."total" DESC '
            .'LIMIT 10';

        self::assertSame($expected, $sql);
    }

    public function testPgsqlSubqueryInWhere(): void
    {
        $qb = $this->getQueryBuilder();

        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('orders')
            ->where()->gte('total', 1000)->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->inSubquery('id', $subquery)->end()
            ->build(true)
        ;

        $expected = 'SELECT * FROM "users" WHERE ("id" IN '
            .'(SELECT "user_id" FROM "orders" WHERE ("total" >= 1000)))';

        self::assertSame($expected, $sql);
    }

    public function testPgsqlCteSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        self::assertSame('SELECT "id", "name" FROM "users"', $sql);
    }

    public function testPgsqlDistinct(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT "user_id" FROM "orders"', $sql);
    }

    public function testPgsqlDistinctMultipleFields(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id', 'status')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT "user_id", "status" FROM "orders"', $sql);
    }

    public function testPgsqlDistinctWithWhere(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')
            ->where()->eq('status', 'completed')->end()
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT "user_id" FROM "orders" WHERE ("status" = \'completed\')', $sql);
    }

    public function testPgsqlDistinctWithOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('category');
        $sql = $qb->select('category')->distinct()->from('products')
            ->orderBy($orderBy)
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT "category" FROM "products" ORDER BY "category" ASC', $sql);
    }

    public function testPgsqlIgnoresFinalModifier(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select()->from('users')->final()->build(true);

        self::assertSame('SELECT * FROM "users"', $sql);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_POSTGRESQL);

        return $qb;
    }
}
