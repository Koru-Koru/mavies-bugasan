<?php

namespace App\Domain;

final class FulfillmentMethod
{
    public const PICKUP = 'Pickup';
    public const IN_HOUSE = 'In-house';
    public const COURIER = 'Courier';

    public const ALL = [self::PICKUP, self::IN_HOUSE, self::COURIER];

    /**
     * In-house and Courier require a delivery address (integrity check #15).
     *
     * @var list<string>
     */
    public const REQUIRE_ADDRESS = [self::IN_HOUSE, self::COURIER];

    public static function requiresAddress(string $method): bool
    {
        return in_array($method, self::REQUIRE_ADDRESS, true);
    }
}
