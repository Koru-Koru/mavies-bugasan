<?php

namespace App\Exceptions\Domain;

class IllegalTransitionException extends DomainException
{
    public static function from(string $from, string $to): self
    {
        return new self(
            "An order cannot go from \"{$from}\" to \"{$to}\".",
            ['from' => $from, 'to' => $to]
        );
    }
}
