<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core schema — converted verbatim from sql/01_schema.sql (the single source
 * of truth). Every table, index, FK and CHECK constraint carries over; nothing
 * is redesigned or "improved" (spec §4.3).
 *
 * One deliberate change from the raw dump: it is a fresh database, so
 * payments.order_id is declared BIGINT UNSIGNED NULL from the start rather
 * than ALTERed. It stays BIGINT (not the spec's INT UNSIGNED) because the FK
 * to orders(order_id BIGINT) requires identical types — a mismatch would make
 * the foreign key uncreatable (inconsistency I4).
 *
 * payment_allocations is the spec §4.4 multi-order payment junction.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        // 1. ADMINS
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS admins (
                admin_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_name           VARCHAR(90)  NOT NULL,
                email                VARCHAR(190) NOT NULL,
                password_hash        VARCHAR(255) NOT NULL,
                is_active            TINYINT(1)   NOT NULL DEFAULT 1,
                failed_login_count   TINYINT UNSIGNED NOT NULL DEFAULT 0,
                locked_until         DATETIME     NULL,
                last_login           DATETIME     NULL,
                last_login_ip        VARBINARY(16) NULL,
                created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_admins_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 2. CUSTOMERS
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS customers (
                customer_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                first_name        VARCHAR(60)  NOT NULL,
                last_name         VARCHAR(90)  NOT NULL,
                phone             VARCHAR(20)  NOT NULL,
                email             VARCHAR(190) NULL,
                password_hash     VARCHAR(255) NULL,
                address_bldg      VARCHAR(90)  NULL,
                address_street    VARCHAR(120) NULL,
                address_brgy      VARCHAR(120) NULL,
                address_city      VARCHAR(90)  NOT NULL DEFAULT '',
                address_notes     VARCHAR(200) NULL,
                email_verified    TINYINT(1)   NOT NULL DEFAULT 0,
                verified_at       DATETIME     NULL,
                is_active         TINYINT(1)   NOT NULL DEFAULT 1,
                is_walk_in        TINYINT(1)   NOT NULL DEFAULT 0,
                blacklisted       TINYINT(1)   NOT NULL DEFAULT 0,
                blacklist_reason  VARCHAR(255) NULL,
                blacklisted_at    DATETIME     NULL,
                blacklisted_by    INT UNSIGNED NULL,
                last_login        DATETIME     NULL,
                created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_customers_phone (phone),
                UNIQUE KEY uq_customers_email (email),
                KEY idx_customers_name (last_name, first_name),
                KEY idx_customers_active (is_active, blacklisted),
                CONSTRAINT fk_customers_blacklisted_by FOREIGN KEY (blacklisted_by)
                    REFERENCES admins(admin_id) ON DELETE SET NULL,
                CONSTRAINT chk_customers_blacklisted CHECK (
                    blacklisted = 0 OR (blacklist_reason IS NOT NULL AND blacklisted_at IS NOT NULL)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 3. AUTH TOKENS
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS auth_tokens (
                token_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                subject    ENUM('customer','admin') NOT NULL,
                subject_id INT UNSIGNED    NOT NULL,
                purpose    ENUM('verify_email','password_reset') NOT NULL,
                token_hash CHAR(64)        NOT NULL,
                expires_at DATETIME        NOT NULL,
                used_at    DATETIME        NULL,
                created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_auth_tokens_hash (token_hash),
                KEY idx_auth_tokens_lookup (subject, subject_id, purpose, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 4. RATE LIMITS (fixed-window counters)
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS rate_limits (
                bucket       VARCHAR(80)  NOT NULL,
                identifier   VARCHAR(190) NOT NULL,
                attempts     INT UNSIGNED NOT NULL DEFAULT 0,
                window_start DATETIME     NOT NULL,
                window_end   DATETIME     NOT NULL,
                PRIMARY KEY (bucket, identifier),
                KEY idx_rate_limits_window (window_end)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 5. INVENTORY
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS inventory (
                inventory_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name               VARCHAR(80)  NOT NULL,
                category           ENUM('Local','Imported','Japanese','Thailand') NOT NULL DEFAULT 'Local',
                unit_price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                wholesale_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                stock              INT NOT NULL DEFAULT 0,
                low_stock_threshold INT NOT NULL DEFAULT 10,
                is_active          TINYINT(1) NOT NULL DEFAULT 1,
                archived_at        DATETIME NULL,
                created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_inventory_name (name),
                KEY idx_inventory_active (is_active),
                CONSTRAINT chk_inventory_stock   CHECK (stock >= 0),
                CONSTRAINT chk_inventory_prices  CHECK (unit_price >= 0 AND wholesale_price >= 0),
                CONSTRAINT chk_inventory_sacks   CHECK (stock <= 100000)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 6. ORDERS (header only — no rice, no money)
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS orders (
                order_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_code         VARCHAR(20) NOT NULL,
                customer_id        INT UNSIGNED NOT NULL,
                fulfillment_method ENUM('Pickup','In-house','Courier') NOT NULL DEFAULT 'Pickup',
                address_bldg       VARCHAR(90)  NULL,
                address_street     VARCHAR(120) NULL,
                address_brgy       VARCHAR(120) NULL,
                address_city       VARCHAR(90)  NULL,
                address_notes      VARCHAR(200) NULL,
                status             ENUM('Pending','Confirmed','Ready for Pickup',
                                       'Out for Delivery','Delivered','Cancelled')
                                   NOT NULL DEFAULT 'Pending',
                confirmed_total    DECIMAL(12,2) NULL,
                staff_notes        TEXT         NULL,
                created_by_admin   INT UNSIGNED NULL,
                confirmed_by_admin INT UNSIGNED NULL,
                confirmed_at       DATETIME     NULL,
                delivered_at       DATETIME     NULL,
                cancelled_at       DATETIME     NULL,
                cancel_reason      VARCHAR(255) NULL,
                created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_orders_code (order_code),
                KEY idx_orders_customer (customer_id, created_at),
                KEY idx_orders_status (status, created_at),
                KEY idx_orders_delivered (delivered_at),
                KEY idx_orders_created (created_at),
                CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
                    REFERENCES customers(customer_id) ON DELETE RESTRICT,
                CONSTRAINT fk_orders_created_by FOREIGN KEY (created_by_admin)
                    REFERENCES admins(admin_id) ON DELETE SET NULL,
                CONSTRAINT fk_orders_confirmed_by FOREIGN KEY (confirmed_by_admin)
                    REFERENCES admins(admin_id) ON DELETE SET NULL,
                CONSTRAINT chk_orders_delivered_at CHECK (
                    status <> 'Delivered' OR delivered_at IS NOT NULL
                ),
                CONSTRAINT chk_orders_cancelled CHECK (
                    status <> 'Cancelled' OR cancelled_at IS NOT NULL
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 7. ORDER ITEMS
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_items (
                order_item_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id         BIGINT UNSIGNED NOT NULL,
                inventory_id     INT UNSIGNED NOT NULL,
                variety_name     VARCHAR(80)  NOT NULL,
                sacks            INT NOT NULL,
                unit_price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                final_unit_price DECIMAL(10,2) NULL,
                staff_note       VARCHAR(255) NULL,
                subtotal         DECIMAL(12,2) GENERATED ALWAYS AS
                                    (sacks * COALESCE(final_unit_price, unit_price)) STORED,
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_items_order (order_id),
                KEY idx_items_inventory (inventory_id),
                CONSTRAINT fk_items_order FOREIGN KEY (order_id)
                    REFERENCES orders(order_id) ON DELETE CASCADE,
                CONSTRAINT fk_items_inventory FOREIGN KEY (inventory_id)
                    REFERENCES inventory(inventory_id) ON DELETE RESTRICT,
                CONSTRAINT chk_items_sacks CHECK (sacks > 0),
                CONSTRAINT chk_items_sacks_max CHECK (sacks <= 500),
                CONSTRAINT chk_items_price CHECK (unit_price >= 0),
                CONSTRAINT chk_items_final_price CHECK (final_unit_price IS NULL OR final_unit_price >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 8. PAYMENTS (parent record; order_id now nullable — spec §4.4)
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payments (
                payment_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id     BIGINT UNSIGNED NULL,
                amount       DECIMAL(12,2) NOT NULL,
                method       ENUM('Cash','Bank Transfer','GCash','Maya','Check','Other')
                             NOT NULL DEFAULT 'Cash',
                reference_no VARCHAR(80) NULL,
                received_by  INT UNSIGNED NOT NULL,
                note         VARCHAR(255) NULL,
                received_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                voided_at    DATETIME NULL,
                voided_by    INT UNSIGNED NULL,
                void_reason  VARCHAR(255) NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_payments_order (order_id),
                KEY idx_payments_received (received_at),
                KEY idx_payments_active (order_id, voided_at),
                CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
                    REFERENCES orders(order_id) ON DELETE CASCADE,
                CONSTRAINT fk_payments_admin FOREIGN KEY (received_by)
                    REFERENCES admins(admin_id) ON DELETE RESTRICT,
                CONSTRAINT chk_payments_amount CHECK (amount > 0),
                CONSTRAINT chk_payments_void CHECK (
                    (voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL)
                    OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 8b. PAYMENT ALLOCATIONS (new — multi-order payments, spec §4.4)
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payment_allocations (
                allocation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                payment_id    BIGINT UNSIGNED NOT NULL,
                order_id      BIGINT UNSIGNED NOT NULL,
                amount        DECIMAL(12,2) NOT NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_alloc_payment (payment_id),
                KEY idx_alloc_order (order_id),
                CONSTRAINT fk_alloc_payment FOREIGN KEY (payment_id)
                    REFERENCES payments(payment_id) ON DELETE CASCADE,
                CONSTRAINT fk_alloc_order FOREIGN KEY (order_id)
                    REFERENCES orders(order_id) ON DELETE CASCADE,
                CONSTRAINT chk_alloc_amount CHECK (amount > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 9. ORDER STATUS HISTORY
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_status_history (
                history_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id    BIGINT UNSIGNED NOT NULL,
                from_status VARCHAR(30) NULL,
                to_status   VARCHAR(30)  NOT NULL,
                changed_by  INT UNSIGNED NULL,
                note        VARCHAR(255) NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_history_order (order_id, created_at),
                CONSTRAINT fk_history_order FOREIGN KEY (order_id)
                    REFERENCES orders(order_id) ON DELETE CASCADE,
                CONSTRAINT fk_history_admin FOREIGN KEY (changed_by)
                    REFERENCES admins(admin_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 10. DAMAGE LOGS
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS damage_logs (
                damage_log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                damage_code   VARCHAR(20) NOT NULL,
                inventory_id  INT UNSIGNED NOT NULL,
                variety_name  VARCHAR(80)  NOT NULL,
                damage_type   VARCHAR(80)  NOT NULL,
                sacks         INT NOT NULL,
                date_logged   DATE NOT NULL,
                notes         TEXT NULL,
                logged_by     INT UNSIGNED NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_damage_code (damage_code),
                KEY idx_damage_date (date_logged),
                KEY idx_damage_inventory (inventory_id),
                CONSTRAINT fk_damage_inventory FOREIGN KEY (inventory_id)
                    REFERENCES inventory(inventory_id) ON DELETE RESTRICT,
                CONSTRAINT fk_damage_admin FOREIGN KEY (logged_by)
                    REFERENCES admins(admin_id) ON DELETE SET NULL,
                CONSTRAINT chk_damage_sacks CHECK (sacks > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 11. STOCK MOVEMENTS (the ledger behind every stock number)
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS stock_movements (
                movement_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inventory_id INT UNSIGNED NOT NULL,
                move_type    ENUM('In','Out','Adjustment') NOT NULL,
                qty          INT NOT NULL,
                reason       VARCHAR(200) NOT NULL,
                ref_type     ENUM('order','damage','manual','seed') NULL,
                ref_id       BIGINT UNSIGNED NULL,
                created_by   INT UNSIGNED NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_movements_inventory (inventory_id, created_at),
                CONSTRAINT fk_movements_inventory FOREIGN KEY (inventory_id)
                    REFERENCES inventory(inventory_id) ON DELETE RESTRICT,
                CONSTRAINT fk_movements_admin FOREIGN KEY (created_by)
                    REFERENCES admins(admin_id) ON DELETE SET NULL,
                CONSTRAINT chk_movements_qty CHECK (qty <> 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 12. TRANSACTION LOG (the audit trail)
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS transaction_log (
                log_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_type  ENUM('customer','admin','system') NOT NULL DEFAULT 'system',
                actor_id    INT UNSIGNED NULL,
                actor_label VARCHAR(120) NULL,
                action_type VARCHAR(60) NOT NULL,
                description VARCHAR(400) NULL,
                entity_type VARCHAR(40) NULL,
                entity_id   BIGINT UNSIGNED NULL,
                ip_address  VARBINARY(16) NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_log_created (created_at),
                KEY idx_log_action (action_type, created_at),
                KEY idx_log_actor (actor_type, actor_id, created_at),
                KEY idx_log_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 13. SETTINGS
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                setting_key   VARCHAR(60) PRIMARY KEY,
                setting_value VARCHAR(400) NOT NULL,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'settings',
            'transaction_log',
            'stock_movements',
            'damage_logs',
            'order_status_history',
            'payment_allocations',
            'payments',
            'order_items',
            'orders',
            'inventory',
            'rate_limits',
            'auth_tokens',
            'customers',
            'admins',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
