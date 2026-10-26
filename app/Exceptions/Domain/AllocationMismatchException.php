<?php

namespace App\Exceptions\Domain;

class AllocationMismatchException extends DomainException
{
    public static function sum(string $allocated, string $payment): self
    {
        return new self(
            "Allocations total ₱{$allocated} but the payment is ₱{$payment}.",
            ['allocated' => $allocated, 'payment' => $payment]
        );
    }
}
