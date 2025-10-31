<?php

namespace QBuilder\Exceptions;

/**
 * Base exception for all QueryBuilder exceptions.
 *
 * This exception serves as a parent for all QueryBuilder-specific exceptions,
 * allowing to catch all QueryBuilder errors with a single catch block.
 */
class QBuilderException extends \PDOException
{
    public function __construct(
        string $message = 'QueryBuilder error',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
