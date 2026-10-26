<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UI milestone: the sign-up flow asks the customer whether they are a new or
 * returning customer (customer_type), and requires them to accept the terms of
 * service before an account can be created (terms_accepted_at).
 *
 * Invariant 17 depends on this: every non-walk-in customer must have signed the
 * terms. Existing rows are backfilled to their created_at so the invariant is
 * satisfied without blocking the live database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('customer_type', ['New', 'Returning'])
                ->default('New')
                ->after('is_walk_in');
            $table->dateTime('terms_accepted_at')
                ->nullable()
                ->after('customer_type');
        });

        DB::table('customers')
            ->where('is_walk_in', 0)
            ->whereNull('terms_accepted_at')
            ->update(['terms_accepted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['customer_type', 'terms_accepted_at']);
        });
    }
};