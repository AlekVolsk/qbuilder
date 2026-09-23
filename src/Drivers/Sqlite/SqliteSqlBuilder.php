<?php

declare(strict_types=1);

namespace QBuilder\Drivers\Sqlite;

use QBuilder\Drivers\AbstractSqlBuilder;
use QBuilder\Exceptions\UnsupportedFeatureException;

/**
 * SQL query builder for SQLite.
 *
 * Responsible for forming final SQL from query settings
 * taking into account SQLite specifics.
 */
class SqliteSqlBuilder extends AbstractSqlBuilder
{
    #[\Override]
    public function buildProcedure(): string
    {
        throw new UnsupportedFeatureException('SQLite does not support stored procedures');
    }
}
