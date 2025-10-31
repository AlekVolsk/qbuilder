<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;

/**
 * @internal
 *
 * @coversNothing
 */
final class FieldTest extends TestCase
{
    public function testFieldSetSimple(): void
    {
        $field = Field::set('name');

        self::assertSame('name', $field->name);
        self::assertSame('', $field->tableOrAlias);
        self::assertSame('', $field->getFieldAlias());
        self::assertFalse($field->isExpression());
    }

    public function testFieldSetWithTable(): void
    {
        $field = Field::set('name', 'users');

        self::assertSame('name', $field->name);
        self::assertSame('users', $field->tableOrAlias);
        self::assertSame('', $field->getFieldAlias());
        self::assertFalse($field->isExpression());
    }

    public function testFieldSetWithAlias(): void
    {
        $field = Field::set('name', '', 'user_name');

        self::assertSame('name', $field->name);
        self::assertSame('', $field->tableOrAlias);
        self::assertSame('user_name', $field->getFieldAlias());
        self::assertFalse($field->isExpression());
    }

    public function testFieldSetWithTableAndAlias(): void
    {
        $field = Field::set('name', 'users', 'user_name');

        self::assertSame('name', $field->name);
        self::assertSame('users', $field->tableOrAlias);
        self::assertSame('user_name', $field->getFieldAlias());
        self::assertFalse($field->isExpression());
    }

    public function testFieldRawExpression(): void
    {
        $field = Field::set('COUNT(*)');

        self::assertSame('COUNT(*)', $field->name);
        self::assertTrue($field->isExpression());
    }

    public function testFieldRawWithAlias(): void
    {
        $field = Field::set('COUNT(*)', '', 'total');

        self::assertSame('COUNT(*)', $field->name);
        self::assertSame('total', $field->getFieldAlias());
        self::assertTrue($field->isExpression());
    }

    public function testFieldExpressionDetection(): void
    {
        $field1 = Field::set('SUM(amount)');
        self::assertTrue($field1->isExpression());

        $field2 = Field::set('COUNT(*)');
        self::assertTrue($field2->isExpression());

        $field3 = Field::set('user.name');
        self::assertFalse($field3->isExpression());

        $field4 = Field::set('name');
        self::assertFalse($field4->isExpression());
    }

    public function testFieldInvalidNameThrowsException(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid field');

        Field::set('invalid field');
    }

    public function testFieldInvalidTableThrowsException(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid table/alias');

        Field::set('name', 'invalid-table');
    }

    public function testFieldInvalidAliasThrowsException(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid alias');

        Field::set('name', '', 'invalid-alias');
    }

    public function testFieldEmptyNameThrowsException(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Field name cannot be empty');

        Field::set('');
    }

    public function testFieldAllowsUnderscoreAndNumbers(): void
    {
        $field = Field::set('user_id_123', 'table_name_456', 'alias_789');

        self::assertSame('user_id_123', $field->name);
        self::assertSame('table_name_456', $field->tableOrAlias);
        self::assertSame('alias_789', $field->getFieldAlias());
    }

    public function testFieldAllowsAsterisk(): void
    {
        $field = Field::set('*');

        self::assertSame('*', $field->name);
        self::assertFalse($field->isExpression());
    }

    public function testFieldComplexExpression(): void
    {
        $field = Field::set("CONCAT(first_name, ' ', last_name)", '', 'full_name');

        self::assertSame("CONCAT(first_name, ' ', last_name)", $field->name);
        self::assertSame('full_name', $field->getFieldAlias());
        self::assertTrue($field->isExpression());
    }

    public function testFieldRedetectWithDriver(): void
    {
        $field = Field::set('string_agg(name, \',\')');

        self::assertTrue($field->isExpression());

        $field->redetectWithDriver('mysql');
        self::assertFalse($field->isExpression());

        $field->redetectWithDriver('pgsql');
        self::assertTrue($field->isExpression());
    }

    public function testFieldRedetectWithDriverOracle(): void
    {
        $field = Field::set('NVL(price, 0)');

        self::assertTrue($field->isExpression());

        $field->redetectWithDriver('mysql');
        self::assertFalse($field->isExpression());

        $field->redetectWithDriver('oci');
        self::assertTrue($field->isExpression());
    }

    public function testFieldRedetectWithDriverMssql(): void
    {
        $field = Field::set('ISNULL(amount, 0)');

        self::assertTrue($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_MYSQL);
        self::assertFalse($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_MSSQL);
        self::assertTrue($field->isExpression());
    }

    public function testFieldRedetectDoesNotAffectCommonFunctions(): void
    {
        $field = Field::set('COUNT(*)');

        self::assertTrue($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_POSTGRESQL);
        self::assertTrue($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_ORACLE);
        self::assertTrue($field->isExpression());
    }
}
