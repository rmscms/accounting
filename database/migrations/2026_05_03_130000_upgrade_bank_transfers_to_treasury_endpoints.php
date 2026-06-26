<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->string('from_treasury_type', 20)->nullable()->after('to_bank_id');
            $table->unsignedBigInteger('from_treasury_id')->nullable()->after('from_treasury_type');
            $table->string('to_treasury_type', 20)->nullable()->after('from_treasury_id');
            $table->unsignedBigInteger('to_treasury_id')->nullable()->after('to_treasury_type');

            $table->index(['from_treasury_type', 'from_treasury_id'], 'bank_transfers_from_treasury_idx');
            $table->index(['to_treasury_type', 'to_treasury_id'], 'bank_transfers_to_treasury_idx');
        });

        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->foreignId('from_bank_id')->nullable()->change();
            $table->foreignId('to_bank_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->dropIndex('bank_transfers_from_treasury_idx');
            $table->dropIndex('bank_transfers_to_treasury_idx');
            $table->dropColumn([
                'from_treasury_type',
                'from_treasury_id',
                'to_treasury_type',
                'to_treasury_id',
            ]);
        });

        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->foreignId('from_bank_id')->nullable(false)->change();
            $table->foreignId('to_bank_id')->nullable(false)->change();
        });
    }
};

