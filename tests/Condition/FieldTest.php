<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class FieldTest
{
    public function testFieldSetSimple(): void
    {
        $field = Field::set('name');

        Assert::same($field->name, 'name');
        Assert::same($field->tableOrAlias, '');
        Assert::same($field->getFieldAlias(), '');
        Assert::false($field->isExpression());
    }

    public function testFieldSetWithTable(): void
    {
        $field = Field::set('name', 'users');

        Assert::same($field->name, 'name');
        Assert::same($field->tableOrAlias, 'users');
        Assert::same($field->getFieldAlias(), '');
        Assert::false($field->isExpression());
    }

    public function testFieldSetWithAlias(): void
    {
        $field = Field::set('name', '', 'user_name');

        Assert::same($field->name, 'name');
        Assert::same($field->tableOrAlias, '');
        Assert::same($field->getFieldAlias(), 'user_name');
        Assert::false($field->isExpression());
    }

    public function testFieldSetWithTableAndAlias(): void
    {
        $field = Field::set('name', 'users', 'user_name');

        Assert::same($field->name, 'name');
        Assert::same($field->tableOrAlias, 'users');
        Assert::same($field->getFieldAlias(), 'user_name');
        Assert::false($field->isExpression());
    }

    public function testFieldRawExpression(): void
    {
        $field = Field::set('COUNT(*)');

        Assert::same($field->name, 'COUNT(*)');
        Assert::true($field->isExpression());
    }

    public function testFieldRawWithAlias(): void
    {
        $field = Field::set('COUNT(*)', '', 'total');

        Assert::same($field->name, 'COUNT(*)');
        Assert::same($field->getFieldAlias(), 'total');
        Assert::true($field->isExpression());
    }

    public function testFieldExpressionDetection(): void
    {
        $field1 = Field::set('SUM(amount)');
        Assert::true($field1->isExpression());

        $field2 = Field::set('COUNT(*)');
        Assert::true($field2->isExpression());

        $field3 = Field::set('user.name');
        Assert::false($field3->isExpression());

        $field4 = Field::set('name');
        Assert::false($field4->isExpression());
    }

    public function testFieldInvalidNameThrowsException(): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid field');

        Field::set('invalid field');
    }

    public function testFieldInvalidTableThrowsException(): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid table/alias');

        Field::set('name', 'invalid-table');
    }

    public function testFieldInvalidAliasThrowsException(): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid alias');

        Field::set('name', '', 'invalid-alias');
    }

    public function testFieldEmptyNameThrowsException(): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Field name cannot be empty');

        Field::set('');
    }

    public function testFieldAllowsUnderscoreAndNumbers(): void
    {
        $field = Field::set('user_id_123', 'table_name_456', 'alias_789');

        Assert::same($field->name, 'user_id_123');
        Assert::same($field->tableOrAlias, 'table_name_456');
        Assert::same($field->getFieldAlias(), 'alias_789');
    }

    public function testFieldAllowsAsterisk(): void
    {
        $field = Field::set('*');

        Assert::same($field->name, '*');
        Assert::false($field->isExpression());
    }

    public function testFieldComplexExpression(): void
    {
        $field = Field::set("CONCAT(first_name, ' ', last_name)", '', 'full_name');

        Assert::same($field->name, "CONCAT(first_name, ' ', last_name)");
        Assert::same($field->getFieldAlias(), 'full_name');
        Assert::true($field->isExpression());
    }

    public function testFieldRedetectWithDriver(): void
    {
        $field = Field::set('string_agg(name, \',\')');

        Assert::true($field->isExpression());

        $field->redetectWithDriver('mysql');
        Assert::false($field->isExpression());

        $field->redetectWithDriver('pgsql');
        Assert::true($field->isExpression());
    }

    public function testFieldRedetectWithDriverOracle(): void
    {
        $field = Field::set('NVL(price, 0)');

        Assert::true($field->isExpression());

        $field->redetectWithDriver('mysql');
        Assert::false($field->isExpression());

        $field->redetectWithDriver('oci');
        Assert::true($field->isExpression());
    }

    public function testFieldRedetectWithDriverMssql(): void
    {
        $field = Field::set('ISNULL(amount, 0)');

        Assert::true($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_MYSQL);
        Assert::false($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_MSSQL);
        Assert::true($field->isExpression());
    }

    public function testFieldRedetectDoesNotAffectCommonFunctions(): void
    {
        $field = Field::set('COUNT(*)');

        Assert::true($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_POSTGRESQL);
        Assert::true($field->isExpression());

        $field->redetectWithDriver(QbConsts::DRIVER_ORACLE);
        Assert::true($field->isExpression());
    }

    #[DataProvider('provideRedetectWithDriverCases')]
    public function testRedetectWithDriverRecognizesOperatorsAndCase(string $name, bool $expected): void
    {
        Assert::same(Field::set($name)->redetectWithDriver(QbConsts::DRIVER_SQLITE)->isExpression(), $expected);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideRedetectWithDriverCases(): iterable
    {
        yield 'arithmetic' => ['price * qty', true];
        yield 'CASE' => ['CASE WHEN a > 1 THEN 1 END', true];
        yield 'plain column' => ['price', false];
    }
}
