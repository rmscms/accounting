<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'bank_id')) {
                $table->foreignId('bank_id')->nullable()->after('payment_id')->constrained('banks')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'cash_box_id')) {
                $table->foreignId('cash_box_id')->nullable()->after('bank_id')->constrained('cash_boxes')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'pos_terminal_id')) {
                $table->foreignId('pos_terminal_id')->nullable()->after('cash_box_id')->constrained('pos_terminals')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('expense_status_histories')) {
            Schema::create('expense_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->unsignedBigInteger('admin_user_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['expense_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_status_histories');

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'pos_terminal_id')) {
                $table->dropForeign(['pos_terminal_id']);
                $table->dropColumn('pos_terminal_id');
            }
            if (Schema::hasColumn('expenses', 'cash_box_id')) {
                $table->dropForeign(['cash_box_id']);
                $table->dropColumn('cash_box_id');
            }
            if (Schema::hasColumn('expenses', 'bank_id')) {
                $table->dropForeign(['bank_id']);
                $table->dropColumn('bank_id');
            }
        });
    }
};
