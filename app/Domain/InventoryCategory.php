<?php

namespace App\Domain;

final class InventoryCategory
{
    public const LOCAL = 'Local';
    public const IMPORTED = 'Imported';
    public const JAPANESE = 'Japanese';
    public const THAILAND = 'Thailand';

    public const ALL = [self::LOCAL, self::IMPORTED, self::JAPANESE, self::THAILAND];
}
