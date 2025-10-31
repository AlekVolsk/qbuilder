<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QBuilder\Drivers\DriverFactory;
use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;

/**
 * @internal
 *
 * @coversNothing
 */
final class DriverFactoryTest extends TestCase
{
    #[DataProvider('provideSupportedDrivers')]
    public function testCreateSupportedDriver(string $driverType): void
    {
        $driver = DriverFactory::create($driverType);

        self::assertInstanceOf(DriverInterface::class, $driver);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideSupportedDrivers(): iterable
    {
        yield 'MySQL' => [QbConsts::DRIVER_MYSQL];
        yield 'PDO MySQL' => [QbConsts::DRIVER_PDO_MYSQL];
        yield 'PostgreSQL' => [QbConsts::DRIVER_POSTGRESQL];
        yield 'PgSQL' => [QbConsts::DRIVER_PGSQL];
        yield 'SQLite' => [QbConsts::DRIVER_SQLITE];
        yield 'MS SQL' => [QbConsts::DRIVER_MSSQL];
        yield 'Oracle' => [QbConsts::DRIVER_ORACLE];
        yield 'ClickHouse' => [QbConsts::DRIVER_CLICKHOUSE];
    }

    public function testCreateUnsupportedDriverThrowsException(): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('Unsupported database driver');

        DriverFactory::create('unsupported_driver');
    }

    public function testCreateEmptyDriverThrowsException(): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('Unsupported database driver');

        DriverFactory::create('');
    }

    #[DataProvider('provideSupportedDrivers')]
    public function testIsSupportedReturnsTrueForSupportedDrivers(string $driverType): void
    {
        self::assertTrue(DriverFactory::isSupported($driverType));
    }

    public function testIsSupportedReturnsFalseForUnsupportedDriver(): void
    {
        self::assertFalse(DriverFactory::isSupported('unsupported_driver'));
    }

    public function testIsSupportedReturnsFalseForEmptyString(): void
    {
        self::assertFalse(DriverFactory::isSupported(''));
    }

    public function testGetSupportedDriversReturnsAllDrivers(): void
    {
        $supported = DriverFactory::getSupportedDrivers();

        self::assertContains(QbConsts::DRIVER_MYSQL, $supported);
        self::assertContains(QbConsts::DRIVER_PDO_MYSQL, $supported);
        self::assertContains(QbConsts::DRIVER_POSTGRESQL, $supported);
        self::assertContains(QbConsts::DRIVER_PGSQL, $supported);
        self::assertContains(QbConsts::DRIVER_SQLITE, $supported);
        self::assertContains(QbConsts::DRIVER_MSSQL, $supported);
        self::assertContains(QbConsts::DRIVER_ORACLE, $supported);
        self::assertContains(QbConsts::DRIVER_CLICKHOUSE, $supported);
        self::assertCount(8, $supported);
    }

    public function testCreateReturnsSameInstanceForSameDriver(): void
    {
        $driver1 = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $driver2 = DriverFactory::create(QbConsts::DRIVER_MYSQL);

        self::assertInstanceOf(DriverInterface::class, $driver1);
        self::assertInstanceOf(DriverInterface::class, $driver2);

        self::assertNotSame($driver1, $driver2);
    }

    public function testCreateReturnsDifferentDriversForDifferentTypes(): void
    {
        $mysqlDriver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $pgsqlDriver = DriverFactory::create(QbConsts::DRIVER_PGSQL);

        self::assertInstanceOf(DriverInterface::class, $mysqlDriver);
        self::assertInstanceOf(DriverInterface::class, $pgsqlDriver);
        self::assertNotSame($mysqlDriver, $pgsqlDriver);
    }
}
