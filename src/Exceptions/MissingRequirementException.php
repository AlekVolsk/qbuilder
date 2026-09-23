<?php

declare(strict_types=1);

namespace QBuilder\Exceptions;

/**
 * Exception thrown when a required element is missing.
 *
 * This includes:
 * - Missing WHERE clause (for safety)
 * - Missing UPDATE data
 * - Missing conflict target
 * - Missing base/recursive/final query in CTE
 */
class MissingRequirementException extends QBuilderException
{
    public function __construct(
        string $message = 'Required element is missing',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
