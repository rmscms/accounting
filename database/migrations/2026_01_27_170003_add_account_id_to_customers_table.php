<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'account_id')) {
            return;
        }
        
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('party_id')->constrained('accounts')->onDelete('restrict');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
