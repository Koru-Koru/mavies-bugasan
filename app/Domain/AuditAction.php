<?php

namespace App\Domain;

/**
 * Every mutation writes exactly one of these to transaction_log (BUSINESS_LOGIC
 * §13). All 33 constants the operations name — nothing free-typed, ever.
 */
final class AuditAction
{
    public const SETUP_OWNER_CREATED = 'SETUP_OWNER_CREATED';
    public const ADMIN_LOGIN = 'ADMIN_LOGIN';
    public const ADMIN_LOGIN_FAILED = 'ADMIN_LOGIN_FAILED';
    public const ADMIN_LOCKED = 'ADMIN_LOCKED';
    public const ADMIN_LOGOUT = 'ADMIN_LOGOUT';
    public const STAFF_CREATED = 'STAFF_CREATED';
    public const STAFF_UPDATED = 'STAFF_UPDATED';
    public const PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    public const CUSTOMER_REGISTERED = 'CUSTOMER_REGISTERED';
    public const CUSTOMER_LOGIN = 'CUSTOMER_LOGIN';
    public const CUSTOMER_LOGIN_FAILED = 'CUSTOMER_LOGIN_FAILED';
    public const CUSTOMER_LOGOUT = 'CUSTOMER_LOGOUT';
    public const EMAIL_VERIFIED = 'EMAIL_VERIFIED';
    public const PASSWORD_RESET_REQUESTED = 'PASSWORD_RESET_REQUESTED';
    public const PASSWORD_RESET_COMPLETED = 'PASSWORD_RESET_COMPLETED';
    public const CUSTOMER_UPDATED = 'CUSTOMER_UPDATED';
    public const CUSTOMER_BLACKLISTED = 'CUSTOMER_BLACKLISTED';
    public const CUSTOMER_WHITELISTED = 'CUSTOMER_WHITELISTED';
    public const CUSTOMER_CREATED = 'CUSTOMER_CREATED';
    public const ORDER_CREATED = 'ORDER_CREATED';
    public const ORDER_STATUS_CHANGED = 'ORDER_STATUS_CHANGED';
    public const ORDER_CANCELLED = 'ORDER_CANCELLED';
    public const ORDER_PRICED = 'ORDER_PRICED';
    public const ORDER_NOTED = 'ORDER_NOTED';
    public const ORDER_COURIER_UPDATED = 'ORDER_COURIER_UPDATED';
    public const PAYMENT_RECORDED = 'PAYMENT_RECORDED';
    public const PAYMENT_VOIDED = 'PAYMENT_VOIDED';
    public const INVENTORY_CREATED = 'INVENTORY_CREATED';
    public const INVENTORY_UPDATED = 'INVENTORY_UPDATED';
    public const INVENTORY_ARCHIVED = 'INVENTORY_ARCHIVED';
    public const INVENTORY_RESTORED = 'INVENTORY_RESTORED';
    public const STOCK_ADJUSTED = 'STOCK_ADJUSTED';
    public const DAMAGE_LOGGED = 'DAMAGE_LOGGED';
    public const SETTINGS_UPDATED = 'SETTINGS_UPDATED';

    /** @var list<string> */
    public const ALL = [
        self::SETUP_OWNER_CREATED,
        self::ADMIN_LOGIN,
        self::ADMIN_LOGIN_FAILED,
        self::ADMIN_LOCKED,
        self::ADMIN_LOGOUT,
        self::STAFF_CREATED,
        self::STAFF_UPDATED,
        self::PASSWORD_CHANGED,
        self::CUSTOMER_REGISTERED,
        self::CUSTOMER_LOGIN,
        self::CUSTOMER_LOGIN_FAILED,
        self::CUSTOMER_LOGOUT,
        self::EMAIL_VERIFIED,
        self::PASSWORD_RESET_REQUESTED,
        self::PASSWORD_RESET_COMPLETED,
        self::CUSTOMER_UPDATED,
        self::CUSTOMER_BLACKLISTED,
        self::CUSTOMER_WHITELISTED,
        self::CUSTOMER_CREATED,
        self::ORDER_CREATED,
        self::ORDER_STATUS_CHANGED,
        self::ORDER_CANCELLED,
        self::ORDER_PRICED,
        self::ORDER_NOTED,
        self::ORDER_COURIER_UPDATED,
        self::PAYMENT_RECORDED,
        self::PAYMENT_VOIDED,
        self::INVENTORY_CREATED,
        self::INVENTORY_UPDATED,
        self::INVENTORY_ARCHIVED,
        self::INVENTORY_RESTORED,
        self::STOCK_ADJUSTED,
        self::DAMAGE_LOGGED,
        self::SETTINGS_UPDATED,
    ];
}
