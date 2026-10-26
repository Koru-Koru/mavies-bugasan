<?php

namespace App\Exceptions\Domain;

class AccountLockedException extends DomainException
{
    public static function until(string $until): self
    {
        return new self(
            "Too many failed attempts. Try again after {$until}.",
            ['locked_until' => $until]
        );
    }
}
