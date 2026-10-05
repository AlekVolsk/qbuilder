<?php

declare(strict_types=1);

namespace QBuilder\Exceptions;

/**
 * Exception thrown when a feature is not supported by the database driver.
 *
 * This includes:
 * - Stored procedures not supported
 * - LIMIT WITH TIES not supported
 * - Conflict handlers not supported
 * - Unsupported database driver
 *
 * @api
 */
class UnsupportedFeatureException extends QBuilderException
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod -- overrides default message and code
    public function __construct(
        string $message = 'Feature is not supported by the database driver',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
