<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class OracleDriverTest extends TestCase
{
    public function testOracleQuotingUppercase(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        self::assertSame('SELECT "ID", "NAME" FROM "USERS"', $sql);
    }

    public function testOracleFetchFirstLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10)->build(true);

        self::assertSame('SELECT * FROM "USERS" FETCH FIRST 10 ROWS ONLY', $sql);
    }

    public function testOracleFetchFirstWithTies(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('name', 'salary')
            ->from('employees')
            ->orderBy(ConditionBy::orderBy()->add('salary', '', 'DESC'))
            ->limitWithTies(5)
            ->build(true)
        ;

        self::assertSame(
            'SELECT "NAME", "SALARY" FROM "EMPLOYEES" '
            .'ORDER BY "SALARY" DESC FETCH FIRST 5 ROWS WITH TIES',
            $sql
        );
    }

    public function testOracleOffsetFetch(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->orderBy(ConditionBy::orderBy()->asc('id'))
            ->limit(10, 20)
            ->build(true)
        ;

        $expected = 'SELECT * FROM "USERS" ORDER BY "ID" ASC '
            .'OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertSame($expected, $sql);
    }

    public function testOracleProcedureNoParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_all_users')->build(true);

        self::assertSame('BEGIN "GET_ALL_USERS"; END;', $sql);
    }

    public function testOracleProcedureWithParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_user_by_id', [1, 'active'])->build(true);

        self::assertSame("BEGIN \"GET_USER_BY_ID\"(1, 'active'); END;", $sql);
    }

    public function testOracleMergeUpsert(): void
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

        $expected = 'MERGE INTO "USERS" target '
            .'USING (SELECT 1 AS "ID", \'John\' AS "NAME", 30 AS "AGE" FROM DUAL) source '
            .'ON (target."ID" = source."ID") '
            .'WHEN MATCHED THEN UPDATE SET "NAME" = \'John Updated\', "AGE" = 31 '
            .'WHEN NOT MATCHED THEN INSERT ("ID", "NAME", "AGE") VALUES (source."ID", source."NAME", source."AGE")';

        self::assertSame($expected, $sql);
    }

    public function testOracleInsertSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')->insertRow(['name' => 'John', 'age' => 30])->build(true);

        self::assertSame("INSERT INTO \"USERS\" (\"NAME\", \"AGE\") VALUES ('John', 30)", $sql);
    }

    public function testOracleInsertMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
            ->build(true)
        ;

        $expected = 'INSERT INTO "USERS" ("NAME", "AGE") '
            ."SELECT 'John', 30 FROM DUAL UNION ALL SELECT 'Jane', 25 FROM DUAL";

        self::assertSame($expected, $sql);
    }

    public function testOracleUpdateSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->update('users')->updateRow(['status' => 'inactive'])
            ->where()->eq('id', 1)->end()
            ->build(true)
        ;

        self::assertSame('UPDATE "USERS" SET "STATUS" = \'inactive\' WHERE ("ID" = 1)', $sql);
    }

    public function testOracleDeleteSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->delete('users')->where()->eq('id', 1)->end()->build(true);

        self::assertSame('DELETE FROM "USERS" WHERE ("ID" = 1)', $sql);
    }

    public function testOracleEscapeLikePattern(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build(true)
        ;

        self::assertSame("SELECT * FROM \"USERS\" WHERE (\"NAME\" LIKE '50!%%' ESCAPE '!')", $sql);
    }

    public function testOracleComplexJoin(): void
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

        $expected = 'SELECT "U"."ID", "U"."NAME", "O"."TOTAL" '
            .'FROM "USERS" "U" '
            .'INNER JOIN "ORDERS" "O" ON ("O"."USER_ID" = "U"."ID") '
            .'WHERE ("O"."TOTAL" >= 100) '
            .'ORDER BY "O"."TOTAL" DESC '
            .'FETCH FIRST 10 ROWS ONLY';

        self::assertSame($expected, $sql);
    }

    public function testOracleSubqueryInSelect(): void
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

        $expected = 'SELECT "ID", "NAME", '
            .'(SELECT COUNT(*) FROM "ORDERS" WHERE ("ORDERS"."USER_ID" = "USERS"."ID")) '
            .'AS "ORDER_COUNT" '
            .'FROM "USERS"';

        self::assertSame($expected, $sql);
    }

    public function testOracleWhereExists(): void
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

        $expected = 'SELECT * FROM "USERS" WHERE (EXISTS '
            .'(SELECT 1 FROM "ORDERS" WHERE ("ORDERS"."USER_ID" = "USERS"."ID") '
            .'AND ("TOTAL" >= 1000)))';

        self::assertSame($expected, $sql);
    }

    public function testOracleGroupByHaving(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('status', Field::set('COUNT(*)', '', 'total'))
            ->from('users')
            ->groupBy(ConditionBy::groupBy()->add('status'))
            ->having()->gt(Field::set('COUNT(*)', '', 'total'), 5)->end()
            ->build(true)
        ;

        $expected = 'SELECT "STATUS", COUNT(*) "TOTAL" FROM "USERS" '
            .'GROUP BY "STATUS" HAVING (COUNT(*) > 5)';

        self::assertSame($expected, $sql);
    }

    public function testOracleDistinct(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT "USER_ID" FROM "ORDERS"', $sql);
    }

    public function testOracleDistinctMultipleFields(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id', 'status')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT "USER_ID", "STATUS" FROM "ORDERS"', $sql);
    }

    public function testOracleDistinctWithWhere(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')
            ->where()->eq('status', 'completed')->end()
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT "USER_ID" FROM "ORDERS" WHERE ("STATUS" = \'completed\')', $sql);
    }

    public function testOracleDistinctWithOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('category');
        $sql = $qb->select('category')->distinct()->from('products')
            ->orderBy($orderBy)
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT "CATEGORY" FROM "PRODUCTS" ORDER BY "CATEGORY" ASC', $sql);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_ORACLE);

        return $qb;
    }
}
