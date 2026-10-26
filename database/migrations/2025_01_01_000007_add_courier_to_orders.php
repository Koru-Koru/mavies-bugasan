<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Courier details are entered by the admin so the customer can follow the
 * delivery from View Orders. The link is optional and only shown when set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'courier_name')) {
                $table->string('courier_name', 80)->nullable()->after('payment_proof_path');
            }
            if (! Schema::hasColumn('orders', 'courier_link')) {
                $table->string('courier_link', 500)->nullable()->after('courier_name');
            }
        });

        DB::statement($this->viewSql(true));
    }

    public function down(): void
    {
        DB::statement($this->viewSql(false));

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'courier_link')) {
                $table->dropColumn('courier_link');
            }
            if (Schema::hasColumn('orders', 'courier_name')) {
                $table->dropColumn('courier_name');
            }
        });
    }

    private function viewSql(bool $withCourier): string
    {
        $sql = <<<'SQL'
            CREATE OR REPLACE VIEW v_orders AS
            SELECT
                o.order_id,
                o.order_code,
                o.customer_id,
                c.first_name,
                c.last_name,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                c.phone,
                c.email AS customer_email,
                c.blacklisted,
                o.fulfillment_method,
                o.payment_method,
                o.payment_proof_path,
                /*COURIER*/
                o.status,
                o.staff_notes,
                o.confirmed_total,
                o.created_by_admin,
                o.confirmed_by_admin,
                o.confirmed_at,
                o.delivered_at,
                o.cancelled_at,
                o.cancel_reason,
                o.created_at,
                o.updated_at,

                (SELECT COALESCE(SUM(oi.sacks), 0) FROM order_items oi WHERE oi.order_id = o.order_id) AS total_sacks,
                (SELECT COALESCE(SUM(oi.subtotal), 0) FROM order_items oi WHERE oi.order_id = o.order_id) AS items_subtotal,
                (SELECT COALESCE(SUM(oi.sacks * COALESCE(oi.final_unit_price, oi.unit_price)), 0)
                   FROM order_items oi WHERE oi.order_id = o.order_id) AS items_total,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id AND oi.final_unit_price IS NOT NULL)
                   AS items_priced,

                COALESCE(o.confirmed_total,
                    (SELECT COALESCE(SUM(oi.sacks * COALESCE(oi.final_unit_price, oi.unit_price)), 0)
                       FROM order_items oi WHERE oi.order_id = o.order_id)
                ) AS amount_due,

                (SELECT COALESCE(SUM(pa.amount), 0)
                   FROM payment_allocations pa
                   JOIN payments p ON p.payment_id = pa.payment_id
                  WHERE pa.order_id = o.order_id AND p.voided_at IS NULL) AS amount_paid,

                ROUND(COALESCE(o.confirmed_total,
                    (SELECT COALESCE(SUM(oi.sacks * COALESCE(oi.final_unit_price, oi.unit_price)), 0)
                       FROM order_items oi WHERE oi.order_id = o.order_id)
                ) - (SELECT COALESCE(SUM(pa.amount), 0)
                       FROM payment_allocations pa
                       JOIN payments p ON p.payment_id = pa.payment_id
                      WHERE pa.order_id = o.order_id AND p.voided_at IS NULL), 2) AS balance,

                CASE
                    WHEN o.status = 'Cancelled' THEN 'Cancelled'
                    WHEN ABS(COALESCE(o.confirmed_total,
                        (SELECT COALESCE(SUM(oi.sacks * COALESCE(oi.final_unit_price, oi.unit_price)), 0)
                           FROM order_items oi WHERE oi.order_id = o.order_id)
                    ) - (SELECT COALESCE(SUM(pa.amount), 0)
                           FROM payment_allocations pa
                           JOIN payments p ON p.payment_id = pa.payment_id
                          WHERE pa.order_id = o.order_id AND p.voided_at IS NULL)) < 0.005 THEN 'Paid'
                    WHEN (SELECT COALESCE(SUM(pa.amount), 0)
                            FROM payment_allocations pa
                            JOIN payments p ON p.payment_id = pa.payment_id
                           WHERE pa.order_id = o.order_id AND p.voided_at IS NULL) > 0 THEN 'Partial'
                    ELSE 'Unpaid'
                END AS payment_status,

                CASE WHEN o.status = 'Delivered' THEN 1 ELSE 0 END AS is_delivered,

                CASE WHEN (SELECT COUNT(*) FROM orders o2
                            WHERE o2.customer_id = o.customer_id
                              AND o2.status <> 'Cancelled') >= 2
                     THEN 'Returning' ELSE 'New' END AS classification

            FROM orders o
            JOIN customers c ON c.customer_id = o.customer_id
            SQL;

        return str_replace(
            '/*COURIER*/',
            $withCourier ? "o.courier_name,\n                o.courier_link," : '',
            $sql,
        );
    }
};
