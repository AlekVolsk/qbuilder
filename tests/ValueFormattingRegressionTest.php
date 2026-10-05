<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class ValueFormattingRegressionTest
{
    public function testInsertKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->insert('org')
            ->insertRow(['inn' => '0123456789', 'code' => '1e3', 'qty' => 5, 'price' => 1.5])
            ->build(true)
        ;

        Assert::same($sql, "INSERT INTO `org` (`inn`, `code`, `qty`, `price`) VALUES ('0123456789', '1e3', 5, 1.5)");
    }

    public function testInsertFormatsBoolAsLiteral(): void
    {
        $sql = $this->mysql()->insert('org')->insertRow(['active' => false, 'flag' => true])->build(true);

        Assert::same($sql, 'INSERT INTO `org` (`active`, `flag`) VALUES (0, 1)');
    }

    public function testPgsqlInsertFormatsBoolAsBooleanLiteral(): void
    {
        $sql = (new QueryBuilder(QbConsts::DRIVER_PGSQL))->insert('org')
            ->insertRow(['active' => false, 'inn' => '0012'])
            ->build(true)
        ;

        Assert::same($sql, 'INSERT INTO "org" ("active", "inn") VALUES (FALSE, \'0012\')');
    }

    public function testUpdateKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->update('org')
            ->updateRow(['inn' => '0012', 'active' => false])
            ->where()->eq('id', 1)->end()
            ->build(true)
        ;

        Assert::same($sql, "UPDATE `org` SET `inn` = '0012', `active` = 0 WHERE (`id` = 1)");
    }

    public function testInConditionKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->select('*')->from('org')
            ->where()->in('inn', ['0123456789', 42])->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM `org` WHERE (`inn` IN ('0123456789', 42))");
    }

    public function testProcedureKeepsNumericLookingStringsQuoted(): void
    {
        $sql = $this->mysql()->procedure('p_find', ['00123', '1e3', 42, null, true])->build(true);

        Assert::same($sql, "CALL `p_find`('00123', '1e3', 42, NULL, 1)");
    }

    public function testMssqlMergeKeepsNumericLookingStringsQuoted(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $qb->insert('org')->insertRow(['inn' => '0012', 'qty' => 5, 'active' => false]);

        $sql = $qb->conflictBuilder()
            ->conflictTarget(['inn'])
            ->set('code', '1e3')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO [org] AS target\n"
                . "USING (VALUES ('0012', 5, 0)) AS source ([inn], [qty], [active])\n"
                . "ON target.[inn] = source.[inn]\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET [code] = '1e3'\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT ([inn], [qty], [active])\n"
                . '  VALUES (source.[inn], source.[qty], source.[active]);'
        );
    }

    public function testOracleMergeKeepsNumericLookingStringsQuoted(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_ORACLE);
        $qb->insert('org')->insertRow(['inn' => '0012', 'qty' => 5]);

        $sql = $qb->conflictBuilder()
            ->conflictTarget(['inn'])
            ->set('code', '1e3')
            ->build()
        ;

        Assert::same(
            $sql,
            "MERGE INTO \"ORG\" target\n"
                . 'USING (SELECT \'0012\' AS "INN", 5 AS "QTY" FROM DUAL) source ON (target."INN" = '
                . "source.\"INN\")\n"
                . "WHEN MATCHED THEN\n"
                . "  UPDATE SET \"CODE\" = '1e3'\n"
                . "WHEN NOT MATCHED THEN\n"
                . "  INSERT (\"INN\", \"QTY\")\n"
                . '  VALUES (source."INN", source."QTY")'
        );
    }

    public function testProcedureParametersFormattedByTypeInEveryDialect(): void
    {
        $params = ['00123', 42, true, null];

        Assert::same(
            (new QueryBuilder(QbConsts::DRIVER_PGSQL))->procedure('p', $params)->build(true),
            "CALL \"p\"('00123', 42, TRUE, NULL)"
        );
        Assert::same(
            (new QueryBuilder(QbConsts::DRIVER_MSSQL))->procedure('p', $params)->build(true),
            "EXEC [p] '00123', 42, 1, NULL"
        );
        Assert::same(
            (new QueryBuilder(QbConsts::DRIVER_ORACLE))->procedure('p', $params)->build(true),
            "BEGIN \"P\"('00123', 42, 1, NULL); END;"
        );
    }

    public function testSelectIntegerLiteralIsNotQuoted(): void
    {
        $qb = $this->mysql();
        $exists = $qb->subQuery()->select('1')->from('orders');

        Assert::same(
            $qb->select('*')->from('users')->where()->exists($exists)->end()->build(true),
            'SELECT * FROM `users` WHERE (EXISTS (SELECT 1 FROM `orders`))'
        );
    }

    public function testFieldZeroLiteralWithAlias(): void
    {
        Assert::same($this->mysql()->select(Field::set('0', '', 'zero'))->build(true), 'SELECT 0 AS `zero`');
    }

    private function mysql(): QueryBuilder
    {
        return new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
    }
}
