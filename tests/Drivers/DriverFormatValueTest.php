<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QBuilder\Drivers\DriverFactory;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QbConsts;

/**
 * @internal
 *
 * @coversNothing
 */
final class DriverFormatValueTest extends TestCase
{
    /**
     * @param ?scalar $value
     */
    #[DataProvider('provideFormatValueByPhpTypeCases')]
    public function testFormatValueByPhpType(
        string $driverType,
        bool|float|int|string|null $value,
        string $expected
    ): void {
        self::assertSame($expected, DriverFactory::create($driverType)->formatValue($value));
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
    public function testFormatValueRejectsNonFiniteFloat(float $value): void
    {
        $this->expectException(InvalidQueryException::class);

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
}
