<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\Field;
use QBuilder\Drivers\Mssql\MssqlMergeBuilder;
use QBuilder\Drivers\Oracle\OracleMergeBuilder;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ValueFormattingRegressionTest extends TestCase
{
    public function testInsertKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->insert('org')
            ->insertRow(['inn' => '0123456789', 'code' => '1e3', 'qty' => 5, 'price' => 1.5])
            ->build(true)
        ;

        self::assertSame(
            "INSERT INTO `org` (`inn`, `code`, `qty`, `price`) VALUES ('0123456789', '1e3', 5, 1.5)",
            $sql
        );
    }

    public function testInsertFormatsBoolAsLiteral(): void
    {
        $sql = $this->mysql()->insert('org')->insertRow(['active' => false, 'flag' => true])->build(true);

        self::assertSame('INSERT INTO `org` (`active`, `flag`) VALUES (0, 1)', $sql);
    }

    public function testPgsqlInsertFormatsBoolAsBooleanLiteral(): void
    {
        $sql = (new QueryBuilder(QbConsts::DRIVER_PGSQL))->insert('org')
            ->insertRow(['active' => false, 'inn' => '0012'])
            ->build(true)
        ;

        self::assertSame('INSERT INTO "org" ("active", "inn") VALUES (FALSE, \'0012\')', $sql);
    }

    public function testUpdateKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->update('org')
            ->updateRow(['inn' => '0012', 'active' => false])
            ->where()->eq('id', 1)->end()
            ->build(true)
        ;

        self::assertSame("UPDATE `org` SET `inn` = '0012', `active` = 0 WHERE (`id` = 1)", $sql);
    }

    public function testInConditionKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->select('*')->from('org')
            ->where()->in('inn', ['0123456789', 42])->end()
            ->build(true)
        ;

        self::assertSame("SELECT * FROM `org` WHERE (`inn` IN ('0123456789', 42))", $sql);
    }

    public function testProcedureKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->procedure('p_find', ['00123', '1e3', 42, null, true])->build(true);

        self::assertSame("CALL `p_find`('00123', '1e3', 42, NULL, 1)", $sql);
    }

    public function testMssqlMergeKeepsNumericLookingStringsQuoted(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $qb->insert('org')->insertRow(['inn' => '0012', 'qty' => 5, 'active' => false]);

        $sql = MssqlMergeBuilder::create($qb, $qb->getDriverInstance())
            ->conflictTarget(['inn'])
            ->set('code', '1e3')
            ->build()
        ;

        self::assertStringContainsString("USING (VALUES ('0012', 5, 0))", $sql);
        self::assertStringContainsString("[code] = '1e3'", $sql);
    }

    public function testOracleMergeKeepsNumericLookingStringsQuoted(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $qb->insert('org')->insertRow(['inn' => '0012', 'qty' => 5]);

        $sql = OracleMergeBuilder::create($qb, $qb->getDriverInstance())
            ->conflictTarget(['inn'])
            ->set('code', '1e3')
            ->build()
        ;

        self::assertStringContainsString('SELECT \'0012\' AS "INN", 5 AS "QTY" FROM DUAL', $sql);
    }

    public function testProcedureParametersFormattedByTypeInEveryDialect(): void
    {
        $params = ['00123', 42, true, null];

        self::assertSame(
            "CALL \"p\"('00123', 42, TRUE, NULL)",
            (new QueryBuilder(QbConsts::DRIVER_PGSQL))->procedure('p', $params)->build(true)
        );
        self::assertSame(
            "EXEC [p] '00123', 42, 1, NULL",
            (new QueryBuilder(QbConsts::DRIVER_MSSQL))->procedure('p', $params)->build(true)
        );
        self::assertSame(
            "BEGIN \"P\"('00123', 42, 1, NULL); END;",
            (new QueryBuilder(QbConsts::DRIVER_ORACLE))->procedure('p', $params)->build(true)
        );
    }

    public function testSelectIntegerLiteralIsNotQuoted(): void
    {
        $qb = $this->mysql();
        $exists = $qb->subQuery()->select('1')->from('orders');

        self::assertSame(
            'SELECT * FROM `users` WHERE (EXISTS (SELECT 1 FROM `orders`))',
            $qb->select('*')->from('users')->where()->exists($exists)->end()->build(true)
        );
    }

    public function testFieldZeroLiteralWithAlias(): void
    {
        self::assertSame('SELECT 0 AS `zero`', $this->mysql()->select(Field::set('0', '', 'zero'))->build(true));
    }

    private function mysql(): QueryBuilder
    {
        return new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
    }
}
