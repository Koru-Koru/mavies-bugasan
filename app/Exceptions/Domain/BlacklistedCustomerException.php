<?php

namespace App\Exceptions\Domain;

class BlacklistedCustomerException extends DomainException
{
    public static function for(int $customerId): self
    {
        return new self(
            'This customer is blacklisted and cannot place orders.',
            ['customer_id' => $customerId]
        );
    }
}
