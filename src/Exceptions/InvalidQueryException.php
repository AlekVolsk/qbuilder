<?php

declare(strict_types=1);

namespace QBuilder\Exceptions;

/**
 * Exception thrown when a query is invalid or malformed.
 *
 * This includes:
 * - Subquery must be SELECT statement
 * - Invalid comparison operators
 * - Wrong query type for operation
 *
 * @api
 */
class InvalidQueryException extends QBuilderException
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod -- overrides default message and code
    public function __construct(
        string $message = 'Invalid or malformed query',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
