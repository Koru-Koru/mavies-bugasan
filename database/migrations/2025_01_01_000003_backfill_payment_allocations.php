<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill payment_allocations from legacy payments.order_id (inconsistency I5).
 *
 * Before §4.4, one payment belonged to exactly one order via payments.order_id.
 * Any such payment that has no allocation row gets a single allocation equal
 * to its amount and attributed to the same order. The NOT EXISTS guard makes
 * the migration idempotent — running it twice is a no-op.
 *
 * On a from-scratch database this does nothing; it exists so an existing
 * deployment can be upgraded without losing its payment history.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO payment_allocations (payment_id, order_id, amount, created_at)
            SELECT p.payment_id, p.order_id, p.amount, p.received_at
              FROM payments p
             WHERE p.order_id IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM payment_allocations pa WHERE pa.payment_id = p.payment_id
               )
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM payment_allocations
             WHERE EXISTS (
                 SELECT 1 FROM payments p
                  WHERE p.payment_id = payment_allocations.payment_id
                    AND p.order_id = payment_allocations.order_id
             )
        SQL);
    }
};
