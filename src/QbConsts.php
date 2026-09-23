<?php

declare(strict_types=1);

namespace QBuilder;

/**
 * Constants for QueryBuilder and related classes.
 *
 * Centralized storage of all constants for query builder.
 */
class QbConsts
{
    // Drivers
    public const string DRIVER_PDO_MYSQL = 'pdomysql'; // Default
    public const string DRIVER_MYSQL = 'mysql';
    public const string DRIVER_MSSQL = 'dblib';
    public const string DRIVER_ORACLE = 'oci';
    public const string DRIVER_POSTGRESQL = 'pgsql';
    public const string DRIVER_PGSQL = 'pgsql';
    public const string DRIVER_SQLITE = 'sqlite';
    public const string DRIVER_CLICKHOUSE = 'clickhouse';

    // JOIN types
    public const string JOIN_INNER = 'INNER';
    public const string JOIN_LEFT = 'LEFT';
    public const string JOIN_RIGHT = 'RIGHT';
    public const string JOIN_FULL = 'FULL';
    public const string JOIN_CROSS = 'CROSS';

    // LIKE types
    public const string LIKE_FULL = 'full';
    public const string LIKE_LEFT = 'left';
    public const string LIKE_RIGHT = 'right';

    // ORDER types
    public const string ORDER_ASC = 'ASC';
    public const string ORDER_DESC = 'DESC';

    // GROUP types
    public const string TYPE_ORDER = 'ORDER';
    public const string TYPE_GROUP = 'GROUP';

    /**
     * Get list of supported drivers.
     *
     * @return array<string>
     */
    public static function getSupportedDrivers(): array
    {
        return [
            self::DRIVER_PDO_MYSQL,
            self::DRIVER_MYSQL,
            self::DRIVER_POSTGRESQL,
            self::DRIVER_PGSQL,
            self::DRIVER_SQLITE,
            self::DRIVER_MSSQL,
            self::DRIVER_ORACLE,
            self::DRIVER_CLICKHOUSE,
        ];
    }
}
