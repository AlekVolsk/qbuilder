<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Drivers\DriverFactory;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QbConsts;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class DriverFormatValueTest
{
    #[DataProvider('provideFormatValueByPhpTypeCases')]
    public function testFormatValueByPhpType(
        string $driverType,
        bool|float|int|string|null $value,
        string $expected
    ): void {
        Assert::same(DriverFactory::create($driverType)->formatValue($value), $expected);
    }

    /**
     * @return iterable<string, array{string, ?scalar, string}>
     */
    public static function provideFormatValueByPhpTypeCases(): iterable
    {
        $drivers = [
            QbConsts::DRIVER_PDO_MYSQL,
            QbConsts::DRIVER_PGSQL,
            QbConsts::DRIVER_SQLITE,
            QbConsts::DRIVER_MSSQL,
            QbConsts::DRIVER_ORACLE,
            QbConsts::DRIVER_CLICKHOUSE,
        ];

        foreach ($drivers as $driver) {
            $isPgsql = QbConsts::DRIVER_PGSQL === $driver;

            yield "{$driver} null" => [$driver, null, 'NULL'];

            yield "{$driver} int" => [$driver, 42, '42'];

            yield "{$driver} negative int" => [$driver, -7, '-7'];

            yield "{$driver} float" => [$driver, 1.5, '1.5'];

            yield "{$driver} numeric string" => [$driver, '42', "'42'"];

            yield "{$driver} leading zero string" => [$driver, '0123456789', "'0123456789'"];

            yield "{$driver} exponent string" => [$driver, '1e3', "'1e3'"];

            yield "{$driver} empty string" => [$driver, '', "''"];

            yield "{$driver} true" => [$driver, true, $isPgsql ? 'TRUE' : '1'];

            yield "{$driver} false" => [$driver, false, $isPgsql ? 'FALSE' : '0'];
        }
    }

    #[DataProvider('provideFormatValueRejectsNonFiniteFloatCases')]
    #[ExpectException(InvalidQueryException::class)]
    public function testFormatValueRejectsNonFiniteFloat(float $value): void
    {
        DriverFactory::create(QbConsts::DRIVER_PDO_MYSQL)->formatValue($value);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function provideFormatValueRejectsNonFiniteFloatCases(): iterable
    {
        yield 'INF' => [INF];

        yield '-INF' => [-INF];

        yield 'NAN' => [NAN];
    }

    #[DataProvider('provideNulByteIsRejectedCases')]
    public function testNulByteIsRejected(string $driverType): void
    {
        Expect::exception(InvalidQueryException::class)->withMessageContaining('must not contain NUL bytes');

        DriverFactory::create($driverType)->formatValue("a\0b");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNulByteIsRejectedCases(): iterable
    {
        yield 'PostgreSQL' => [QbConsts::DRIVER_PGSQL];
        yield 'SQLite' => [QbConsts::DRIVER_SQLITE];
    }
}
