<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            if (! Schema::hasColumn('fiscal_years', 'is_closed')) {
                $table->boolean('is_closed')->default(false)->after('is_current');
            }
            if (! Schema::hasColumn('fiscal_years', 'closing_document_id')) {
                $table->unsignedBigInteger('closing_document_id')->nullable()->after('closed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            if (Schema::hasColumn('fiscal_years', 'closing_document_id')) {
                $table->dropColumn('closing_document_id');
            }
            if (Schema::hasColumn('fiscal_years', 'is_closed')) {
                $table->dropColumn('is_closed');
            }
        });
    }
};
