<?php

declare(strict_types=1);

namespace QBuilder\Exceptions;

/**
 * Exception thrown when an invalid identifier is provided.
 *
 * This includes invalid:
 * - Table names
 * - Column/field names
 * - Alias names
 * - Function names
 * - Expression syntax
 * - Operators
 * - Order directions
 */
class InvalidIdentifierException extends QBuilderException
{
    public function __construct(
        string $message = 'Invalid identifier provided',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
