<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هم‌راستا با PurchaseOrderService::receiveItems که وضعیت partially_received می‌نویسد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM(
                'draft','sent','confirmed','partially_received','received','invoiced','cancelled'
            ) NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE purchase_orders SET status = 'confirmed' WHERE status = 'partially_received'");
            DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM(
                'draft','sent','confirmed','received','invoiced','cancelled'
            ) NOT NULL DEFAULT 'draft'");
        }
    }
};
