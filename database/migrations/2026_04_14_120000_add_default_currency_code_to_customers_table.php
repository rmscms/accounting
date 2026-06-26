<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'default_currency_code')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->string('default_currency_code', 3)->nullable();
        });

        if (Schema::hasTable('currencies')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreign('default_currency_code')->references('code')->on('currencies')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['default_currency_code']);
            $table->dropColumn('default_currency_code');
        });
    }
};
