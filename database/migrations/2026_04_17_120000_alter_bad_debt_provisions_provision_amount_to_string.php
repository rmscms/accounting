<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bad_debt_provisions')) {
            return;
        }

        Schema::table('bad_debt_provisions', function (Blueprint $table) {
            $table->string('provision_amount', 32)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bad_debt_provisions')) {
            return;
        }

        Schema::table('bad_debt_provisions', function (Blueprint $table) {
            $table->decimal('provision_amount', 15, 2)->change();
        });
    }
};

