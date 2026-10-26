<?php

namespace App\Domain;

/**
 * The six order statuses and the only transitions allowed between them
 * (BUSINESS_LOGIC.md §1, §2). Illegal jumps are refused with an explanation,
 * never silently accepted.
 */
final class OrderStatus
{
    public const PENDING = 'Pending';
    public const CONFIRMED = 'Confirmed';
    public const READY_FOR_PICKUP = 'Ready for Pickup';
    public const OUT_FOR_DELIVERY = 'Out for Delivery';
    public const DELIVERED = 'Delivered';
    public const CANCELLED = 'Cancelled';

    /** @var list<string> */
    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::READY_FOR_PICKUP,
        self::OUT_FOR_DELIVERY,
        self::DELIVERED,
        self::CANCELLED,
    ];

    /** @var list<string> */
    public const TERMINAL = [self::DELIVERED, self::CANCELLED];

    /**
     * From => the statuses it may become.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::PENDING => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::READY_FOR_PICKUP, self::OUT_FOR_DELIVERY, self::CANCELLED],
        self::READY_FOR_PICKUP => [self::DELIVERED, self::CANCELLED],
        self::OUT_FOR_DELIVERY => [self::DELIVERED, self::CANCELLED],
        self::DELIVERED => [],
        self::CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    /** @return list<string> */
    public static function allowedFrom(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }
}
