<?php

namespace App\Domain;

final class PaymentMethod
{
    public const CASH = 'Cash';
    public const BANK_TRANSFER = 'Bank Transfer';
    public const GCASH = 'GCash';
    public const MAYA = 'Maya';
    public const CHECK = 'Check';
    public const OTHER = 'Other';

    public const ALL = [
        self::CASH,
        self::BANK_TRANSFER,
        self::GCASH,
        self::MAYA,
        self::CHECK,
        self::OTHER,
    ];

    /** What a customer may choose when placing an order. */
    public const CUSTOMER_CHOICES = [
        self::CASH,
        self::GCASH,
        self::MAYA,
    ];
}
