<?php

namespace App\Exceptions\Domain;

class OverpaymentException extends DomainException
{
    public static function for(string $orderCode, string $balance): self
    {
        return new self(
            "Payment exceeds the balance of {$orderCode} (₱{$balance} remaining).",
            ['order_code' => $orderCode, 'balance' => $balance]
        );
    }
}
