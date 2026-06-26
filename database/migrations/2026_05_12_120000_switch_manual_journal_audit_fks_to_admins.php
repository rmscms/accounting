<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('manual_journals')) {
            return;
        }

        Schema::table('manual_journals', function (Blueprint $table): void {
            if (Schema::hasColumn('manual_journals', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
                $table->foreignId('created_by_user_id')->nullable()->constrained('admins')->nullOnDelete();
            }

            if (Schema::hasColumn('manual_journals', 'posted_by_user_id')) {
                $table->dropConstrainedForeignId('posted_by_user_id');
                $table->foreignId('posted_by_user_id')->nullable()->constrained('admins')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('manual_journals')) {
            return;
        }

        Schema::table('manual_journals', function (Blueprint $table): void {
            if (Schema::hasColumn('manual_journals', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }

            if (Schema::hasColumn('manual_journals', 'posted_by_user_id')) {
                $table->dropConstrainedForeignId('posted_by_user_id');
                $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }
};
