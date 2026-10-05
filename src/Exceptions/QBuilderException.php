<?php

declare(strict_types=1);

namespace QBuilder\Exceptions;

/**
 * Base exception for all QueryBuilder exceptions.
 *
 * This exception serves as a parent for all QueryBuilder-specific exceptions,
 * allowing to catch all QueryBuilder errors with a single catch block.
 *
 * @api
 */
class QBuilderException extends \PDOException
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod -- overrides default message and code
    public function __construct(
        string $message = 'QueryBuilder error',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
