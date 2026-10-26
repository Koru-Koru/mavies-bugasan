<?php

namespace App\Domain;

final class ActorType
{
    public const CUSTOMER = 'customer';
    public const ADMIN = 'admin';
    public const SYSTEM = 'system';

    public const ALL = [self::CUSTOMER, self::ADMIN, self::SYSTEM];
}
