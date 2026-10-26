<?php

namespace App\Exceptions\Domain;

class InvalidCredentialsException extends DomainException
{
    public function __construct(string $message = 'Incorrect email or password.')
    {
        parent::__construct($message);
    }
}
