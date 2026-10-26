<?php

namespace App\Domain;

final class DamageType
{
    public const PEST = 'Pest Infestation';
    public const WATER = 'Water Damage';
    public const TORN_SACK = 'Torn Sack / Spillage';
    public const EXPIRED = 'Expired / Spoiled';
    public const OTHER = 'Other';

    public const ALL = [
        self::PEST,
        self::WATER,
        self::TORN_SACK,
        self::EXPIRED,
        self::OTHER,
    ];
}
