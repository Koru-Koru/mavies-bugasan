<?php

namespace App\Domain;

final class RefType
{
    public const ORDER = 'order';
    public const DAMAGE = 'damage';
    public const MANUAL = 'manual';
    public const SEED = 'seed';

    public const ALL = [self::ORDER, self::DAMAGE, self::MANUAL, self::SEED];
}
