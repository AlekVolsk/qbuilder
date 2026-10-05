<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Drivers\DriverFactory;
use QBuilder\Drivers\DriverInterface;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class DriverFactoryTest
{
    #[DataProvider('provideSupportedDrivers')]
    public function testCreateSupportedDriver(string $driverType): void
    {
        $driver = DriverFactory::create($driverType);

        Assert::instanceOf($driver, DriverInterface::class);
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
        Expect::exception(UnsupportedFeatureException::class)->withMessageContaining('Unsupported database driver');

        DriverFactory::create('unsupported_driver');
    }

    public function testCreateEmptyDriverThrowsException(): void
    {
        Expect::exception(UnsupportedFeatureException::class)->withMessageContaining('Unsupported database driver');

        DriverFactory::create('');
    }

    public function testEverySupportedDriverIsCreatable(): void
    {
        $supported = QbConsts::getSupportedDrivers();

        Assert::array(array_values(array_unique($supported)))->sameElementsAs([
            QbConsts::DRIVER_MYSQL,
            QbConsts::DRIVER_PDO_MYSQL,
            QbConsts::DRIVER_PGSQL,
            QbConsts::DRIVER_SQLITE,
            QbConsts::DRIVER_MSSQL,
            QbConsts::DRIVER_ORACLE,
            QbConsts::DRIVER_CLICKHOUSE,
        ]);

        foreach ($supported as $driverType) {
            Assert::instanceOf(DriverFactory::create($driverType), DriverInterface::class);
        }
    }

    public function testCreateReturnsDifferentDriversForDifferentTypes(): void
    {
        $mysqlDriver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $pgsqlDriver = DriverFactory::create(QbConsts::DRIVER_PGSQL);

        Assert::instanceOf($mysqlDriver, DriverInterface::class);
        Assert::instanceOf($pgsqlDriver, DriverInterface::class);
        Assert::notSame($pgsqlDriver, $mysqlDriver);
    }
}
