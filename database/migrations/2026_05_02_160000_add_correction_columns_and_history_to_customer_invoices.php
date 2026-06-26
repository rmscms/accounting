<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_invoices', 'original_invoice_id')) {
                $table->foreignId('original_invoice_id')
                    ->nullable()
                    ->after('document_id')
                    ->constrained('customer_invoices')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_invoices', 'correction_group_id')) {
                $table->string('correction_group_id', 64)
                    ->nullable()
                    ->after('original_invoice_id');
                $table->index('correction_group_id');
            }
        });

        if (! Schema::hasTable('customer_invoice_corrections')) {
            Schema::create('customer_invoice_corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_invoice_id')->constrained('customer_invoices')->cascadeOnDelete();
                $table->string('correction_group_id', 64)->nullable();
                $table->string('action_type', 32);
                $table->foreignId('source_document_id')->nullable()->constrained('accounting_documents')->nullOnDelete();
                $table->foreignId('target_document_id')->nullable()->constrained('accounting_documents')->nullOnDelete();
                $table->foreignId('source_invoice_id')->nullable()->constrained('customer_invoices')->nullOnDelete();
                $table->foreignId('target_invoice_id')->nullable()->constrained('customer_invoices')->nullOnDelete();
                $table->foreignId('credit_note_id')->nullable()->constrained('credit_notes')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('admin_user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['customer_invoice_id', 'created_at']);
                $table->index(['correction_group_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_corrections');

        Schema::table('customer_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('customer_invoices', 'original_invoice_id')) {
                $table->dropForeign(['original_invoice_id']);
                $table->dropColumn('original_invoice_id');
            }
            if (Schema::hasColumn('customer_invoices', 'correction_group_id')) {
                $table->dropIndex(['correction_group_id']);
                $table->dropColumn('correction_group_id');
            }
        });
    }
};
