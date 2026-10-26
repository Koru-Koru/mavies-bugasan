<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The 17 invariants from sql/03_integrity_checks.sql. Every query MUST return
 * zero rows. Run monthly, and any time the dashboard looks wrong — it is far
 * cheaper than discovering a stock discrepancy after the rice is sold.
 *
 * Exits non-zero if any check fails, so it can gate CI.
 */
class IntegrityCheck extends Command
{
    protected $signature = 'mavies:integrity-check';

    protected $description = 'Run the 17 integrity invariant checks (zero rows = pass).';

    /** @var array<string,string> */
    protected array $checks = [
        '1. stock must equal the movement ledger' => <<<'SQL'
            SELECT i.inventory_id, i.name, i.stock AS stock_says,
                   COALESCE(SUM(m.qty), 0) AS ledger_says,
                   i.stock - COALESCE(SUM(m.qty), 0) AS difference
              FROM inventory i
              LEFT JOIN stock_movements m ON m.inventory_id = i.inventory_id
             GROUP BY i.inventory_id, i.name, i.stock
            HAVING i.stock <> COALESCE(SUM(m.qty), 0)
        SQL,
        '2. no negative stock' => <<<'SQL'
            SELECT inventory_id, name, stock FROM inventory WHERE stock < 0
        SQL,
        '3. line subtotals must equal sacks x effective price' => <<<'SQL'
            SELECT order_item_id, order_id, variety_name, sacks, unit_price, final_unit_price, subtotal
              FROM order_items
             WHERE sacks <= 0
                OR ABS(subtotal - (sacks * COALESCE(final_unit_price, unit_price))) > 0.005
        SQL,
        '4. no order without at least one line' => <<<'SQL'
            SELECT o.order_id, o.order_code
              FROM orders o
             WHERE NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id)
        SQL,
        '5. no order paid more than it is due' => <<<'SQL'
            SELECT order_id, order_code, amount_due, amount_paid, balance
              FROM v_orders
             WHERE status <> 'Cancelled'
               AND balance < -0.005
        SQL,
        '6. a delivered order must have delivered_at' => <<<'SQL'
            SELECT order_id, order_code, status FROM orders
             WHERE status = 'Delivered' AND delivered_at IS NULL
        SQL,
        '7. a cancelled order must have cancelled_at' => <<<'SQL'
            SELECT order_id, order_code, status FROM orders
             WHERE status = 'Cancelled' AND cancelled_at IS NULL
        SQL,
        '8. a cancelled order must have no balance owed' => <<<'SQL'
            SELECT order_id, order_code, balance FROM v_orders
             WHERE status = 'Cancelled' AND balance > 0.005
        SQL,
        '9. blacklisted customers must have a reason and a timestamp' => <<<'SQL'
            SELECT customer_id, first_name, last_name, blacklist_reason, blacklisted_at
              FROM customers
             WHERE blacklisted = 1
               AND (blacklist_reason IS NULL OR blacklist_reason = '' OR blacklisted_at IS NULL)
        SQL,
        '10. not blacklisted means no reason left over' => <<<'SQL'
            SELECT customer_id, first_name, last_name FROM customers
             WHERE blacklisted = 0
               AND (blacklist_reason IS NOT NULL OR blacklisted_at IS NOT NULL)
        SQL,
        '11. every order code must match its own id' => <<<'SQL'
            SELECT order_id, order_code FROM orders
             WHERE order_code <> CONCAT('ORD-', LPAD(order_id, 6, '0'))
        SQL,
        '12. every damage code must match its own id' => <<<'SQL'
            SELECT damage_log_id, damage_code FROM damage_logs
             WHERE damage_code <> CONCAT('DMG-', LPAD(damage_log_id, 6, '0'))
        SQL,
        '13. no password stored in anything but a bcrypt hash' => <<<'SQL'
            SELECT customer_id AS ref_id, email, LEFT(password_hash, 4) AS prefix, LENGTH(password_hash) AS length
              FROM customers
             WHERE password_hash IS NOT NULL
               AND (password_hash NOT LIKE '$2y$%' OR LENGTH(password_hash) <> 60)
            UNION ALL
            SELECT admin_id AS ref_id, email, LEFT(password_hash, 4) AS prefix, LENGTH(password_hash) AS length
              FROM admins
             WHERE password_hash NOT LIKE '$2y$%' OR LENGTH(password_hash) <> 60
        SQL,
        '14. no verified customer may be missing an email' => <<<'SQL'
            SELECT customer_id, first_name, last_name, email FROM customers
             WHERE email_verified = 1 AND (email IS NULL OR email = '')
        SQL,
        '15. order addresses must be present for delivery orders' => <<<'SQL'
            SELECT order_id, order_code, fulfillment_method FROM orders
             WHERE fulfillment_method IN ('In-house','Courier')
               AND (address_city IS NULL OR address_city = '')
        SQL,
        '16. no orphan audit entries (entity referenced but gone)' => <<<'SQL'
            SELECT l.log_id, l.action_type, l.entity_type, l.entity_id
              FROM transaction_log l
             WHERE l.entity_type = 'order'
               AND l.entity_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.order_id = l.entity_id)
        SQL,
        '17. every non-walk-in customer must have accepted the terms' => <<<'SQL'
            SELECT customer_id, first_name, last_name, customer_type, terms_accepted_at
              FROM customers
             WHERE is_walk_in = 0
               AND terms_accepted_at IS NULL
        SQL,
    ];

    public function handle(): int
    {
        $failed = 0;

        foreach ($this->checks as $name => $sql) {
            $rows = DB::select($sql);

            if (count($rows) === 0) {
                $this->line("<info>PASS</info>  {$name}");
                continue;
            }

            $failed++;
            $this->error('FAIL  '.$name.' ('.count($rows).' row(s))');
            foreach (array_slice($rows, 0, 5) as $row) {
                $this->line('        '.json_encode($row));
            }
        }

        $summary = DB::selectOne(<<<'SQL'
            SELECT
              (SELECT COUNT(*) FROM orders)                                      AS orders_total,
              (SELECT COUNT(*) FROM orders WHERE status = 'Delivered')           AS delivered,
              (SELECT COUNT(*) FROM orders WHERE status = 'Cancelled')           AS cancelled,
              (SELECT COUNT(*) FROM orders WHERE status NOT IN ('Delivered','Cancelled')) AS open_orders,
              (SELECT COUNT(*) FROM order_items)                                 AS order_lines,
              (SELECT COALESCE(SUM(sacks),0) FROM order_items)                   AS sacks_ordered,
              (SELECT COALESCE(SUM(amount_due),0) FROM v_orders WHERE status='Delivered')  AS delivered_revenue,
              (SELECT COALESCE(SUM(amount_paid),0) FROM v_orders WHERE status='Delivered') AS collected,
              (SELECT COALESCE(SUM(balance),0) FROM v_orders
                 WHERE status = 'Delivered' AND balance > 0.005)                 AS still_owed,
              (SELECT COALESCE(SUM(stock),0) FROM inventory)                     AS sacks_in_stock
        SQL);

        $this->newLine();
        $this->line('SUMMARY  '.json_encode($summary));

        if ($failed === 0) {
            $this->newLine();
            $this->info('All '.count($this->checks).' integrity checks passed.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error($failed.' of '.count($this->checks).' integrity checks FAILED.');

        return self::FAILURE;
    }
}
