<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('suppliers', 'party_id')) {
            return;
        }
        
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('party_id')->nullable()->after('id')->constrained('parties')->onDelete('set null');
            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['party_id']);
            $table->dropIndex(['party_id']);
            $table->dropColumn('party_id');
        });
    }
};
