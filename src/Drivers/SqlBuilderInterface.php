<?php

declare(strict_types=1);

namespace QBuilder\Drivers;

/**
 * SQL query builder interface.
 *
 * Responsible for forming final SQL from query settings
 * taking into account specific driver features.
 *
 * @internal
 */
interface SqlBuilderInterface
{
    /**
     * Build SQL query.
     *
     * @param bool $compact Remove extra newlines and spaces
     *
     * @return string Ready SQL query
     */
    public function build(bool $compact = false): string;

    /**
     * Build SELECT query.
     */
    public function buildSelect(): string;

    /**
     * Build INSERT query.
     */
    public function buildInsert(): string;

    /**
     * Build UPDATE query.
     */
    public function buildUpdate(): string;

    /**
     * Build DELETE query.
     */
    public function buildDelete(): string;

    /**
     * Build CALL/EXEC for stored procedure invocation.
     */
    public function buildProcedure(): string;
}
