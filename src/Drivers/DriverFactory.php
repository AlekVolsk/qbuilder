<?php

declare(strict_types=1);

namespace QBuilder\Drivers;

use QBuilder\Drivers\Clickhouse\ClickhouseDriver;
use QBuilder\Drivers\Mssql\MssqlDriver;
use QBuilder\Drivers\Mysql\MysqlDriver;
use QBuilder\Drivers\Oracle\OracleDriver;
use QBuilder\Drivers\Pgsql\PgsqlDriver;
use QBuilder\Drivers\Sqlite\SqliteDriver;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;

/**
 * Factory for creating database drivers.
 *
 * Automatically selects and creates the appropriate driver based on database type.
 *
 * @internal
 */
final class DriverFactory
{
    /**
     * Create driver for specified database type.
     *
     * @param string $driverType Driver type (from QbConsts::DRIVER_*)
     *
     * @throws UnsupportedFeatureException If driver is not supported
     *
     * @example
     * $driver = DriverFactory::create(QbConsts::DRIVER_PDO_MYSQL);
     */
    public static function create(string $driverType): DriverInterface
    {
        return match ($driverType) {
            QbConsts::DRIVER_MYSQL => new MysqlDriver(),
            QbConsts::DRIVER_PDO_MYSQL => new MysqlDriver(),
            QbConsts::DRIVER_MSSQL => new MssqlDriver(),
            QbConsts::DRIVER_ORACLE => new OracleDriver(),
            QbConsts::DRIVER_POSTGRESQL => new PgsqlDriver(),
            QbConsts::DRIVER_PGSQL => new PgsqlDriver(),
            QbConsts::DRIVER_SQLITE => new SqliteDriver(),
            QbConsts::DRIVER_CLICKHOUSE => new ClickhouseDriver(),

            default => throw new UnsupportedFeatureException(
                "Unsupported database driver: '{$driverType}'. Supported drivers: "
                    . implode(', ', QbConsts::getSupportedDrivers())
            ),
        };
    }
}
