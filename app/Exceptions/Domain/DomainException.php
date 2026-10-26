<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * Base for every business-rule refusal. The message is written for a human
 * and is safe to show (invariant 3 only forbids leaking infrastructure
 * exceptions, e.g. SQL/DSN). The global handler renders these as 422.
 */
class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, protected array $context = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
