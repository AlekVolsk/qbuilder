<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class SqliteDriverTest extends TestCase
{
    public function testSqliteQuoting(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        self::assertSame('SELECT "id", "name" FROM "users"', $sql);
    }

    public function testSqliteLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10)->build(true);

        self::assertSame('SELECT * FROM "users" LIMIT 10', $sql);
    }

    public function testSqliteLimitOffset(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10, 20)->build(true);

        self::assertSame('SELECT * FROM "users" LIMIT 10 OFFSET 20', $sql);
    }

    public function testSqliteLimitWithTiesNotSupported(): void
    {
        $qb = $this->getQueryBuilder();

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('SQLite does not support LIMIT WITH TIES');

        $qb->select('name', 'salary')
            ->from('employees')
            ->orderBy(ConditionBy::orderBy()->add('salary', '', 'DESC'))
            ->limitWithTies(5)
            ->build(true)
        ;
    }

    public function testSqliteOnConflict(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->conflictTarget(['email'])
            ->set('name', 'John Updated')
            ->sqlFunction('updated_at', 'CURRENT_TIMESTAMP')
        ;

        $sql = $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'John'])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO "users" ("email", "name") '
            .'VALUES (\'test@example.com\', \'John\') '
            .'ON CONFLICT ("email") DO UPDATE SET '
            .'"name" = \'John Updated\', "updated_at" = CURRENT_TIMESTAMP';

        self::assertSame($expected, $sql);
    }

    public function testSqliteOnConflictDoNothing(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder();

        $sql = $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'John'])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO "users" ("email", "name") '
            .'VALUES (\'test@example.com\', \'John\')';

        self::assertSame($expected, $sql);
    }

    public function testSqliteOnConflictExcluded(): void
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

    public function testSqliteInsertSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')->insertRow(['name' => 'John', 'age' => 30])->build(true);

        self::assertSame("INSERT INTO \"users\" (\"name\", \"age\") VALUES ('John', 30)", $sql);
    }

    public function testSqliteInsertMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
            ->build(true)
        ;

        $expected = 'INSERT INTO "users" ("name", "age") '
            ."VALUES ('John', 30), ('Jane', 25)";

        self::assertSame($expected, $sql);
    }

    public function testSqliteUpdateSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->update('users')->updateRow(['status' => 'inactive'])
            ->where()->eq('id', 1)->end()
            ->build(true)
        ;

        self::assertSame('UPDATE "users" SET "status" = \'inactive\' WHERE ("id" = 1)', $sql);
    }

    public function testSqliteDeleteSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->delete('users')->where()->eq('id', 1)->end()->build(true);

        self::assertSame('DELETE FROM "users" WHERE ("id" = 1)', $sql);
    }

    public function testSqliteEscapeLikePattern(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM "users" WHERE ("name" LIKE \'50\%\')', $sql);
    }

    public function testSqliteComplexJoin(): void
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
            ->limit(10)
            ->build(true)
        ;

        $expected = 'SELECT "u"."id", "u"."name", "o"."total" '
            .'FROM "users" AS "u" '
            .'LEFT JOIN "orders" AS "o" ON ("o"."user_id" = "u"."id") '
            .'WHERE ("o"."total" >= 100) '
            .'ORDER BY "o"."total" DESC '
            .'LIMIT 10';

        self::assertSame($expected, $sql);
    }

    public function testSqliteSubqueryInSelect(): void
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

        $expected = 'SELECT "id", "name", '
            .'(SELECT COUNT(*) FROM "orders" WHERE ("orders"."user_id" = "users"."id")) '
            .'AS "order_count" '
            .'FROM "users"';

        self::assertSame($expected, $sql);
    }

    public function testSqliteWhereExists(): void
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

        $expected = 'SELECT * FROM "users" WHERE (EXISTS '
            .'(SELECT "1" FROM "orders" WHERE ("orders"."user_id" = "users"."id") AND ("total" >= 1000)))';

        self::assertSame($expected, $sql);
    }

    public function testSqliteProcedureThrowsException(): void
    {
        $qb = $this->getQueryBuilder();

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('SQLite does not support stored procedures');

        $qb->procedure('test_proc')->build(true);
    }

    public function testSqliteGroupByHaving(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having()->gt(Field::set('COUNT(*)', '', 'total'), 5)->end()
            ->build(true)
        ;

        $expected = 'SELECT "status", COUNT(*) AS "total" FROM "users" '
            .'GROUP BY "status" HAVING (COUNT(*) > 5)';

        self::assertSame($expected, $sql);
    }

    public function testSqliteDistinct(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT "user_id" FROM "orders"', $sql);
    }

    public function testSqliteDistinctMultipleFields(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id', 'status')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT "user_id", "status" FROM "orders"', $sql);
    }

    public function testSqliteDistinctWithWhere(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')
            ->where()->eq('status', 'completed')->end()
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT "user_id" FROM "orders" WHERE ("status" = \'completed\')', $sql);
    }

    public function testSqliteDistinctWithOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('category');
        $sql = $qb->select('category')->distinct()->from('products')
            ->orderBy($orderBy)
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT "category" FROM "products" ORDER BY "category" ASC', $sql);
    }

    public function testSqliteIgnoresFinalModifier(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select()->from('users')->final()->build(true);

        self::assertSame('SELECT * FROM "users"', $sql);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_SQLITE);

        return $qb;
    }
}
