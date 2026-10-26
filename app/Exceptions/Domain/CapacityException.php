<?php

namespace App\Exceptions\Domain;

class CapacityException extends DomainException
{
    public static function lines(int $max): self
    {
        return new self("An order may have at most {$max} lines.", ['max' => $max]);
    }

    public static function dailyOrders(int $max): self
    {
        return new self(
            "Unverified customers may place at most {$max} orders per day. Verify your email to lift this limit.",
            ['max' => $max]
        );
    }
}
