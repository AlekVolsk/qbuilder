<?php

namespace QBuilder\Exceptions;

/**
 * Exception thrown when a feature is not supported by the database driver.
 *
 * This includes:
 * - Stored procedures not supported
 * - LIMIT WITH TIES not supported
 * - Conflict handlers not supported
 * - Unsupported database driver
 */
class UnsupportedFeatureException extends QBuilderException
{
    public function __construct(
        string $message = 'Feature is not supported by the database driver',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
