<?php

namespace App\Domain;

/**
 * Signed ledger direction. qty is + for In, - for Out; Adjustment may be
 * either (BUSINESS_LOGIC.md §3). The DB CHECK forbids qty = 0.
 */
final class MoveType
{
    public const IN = 'In';
    public const OUT = 'Out';
    public const ADJUSTMENT = 'Adjustment';

    public const ALL = [self::IN, self::OUT, self::ADJUSTMENT];
}
