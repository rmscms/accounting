<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheques', function (Blueprint $table): void {
            if (! Schema::hasColumn('cheques', 'party_id')) {
                $table->foreignId('party_id')->nullable()->after('bank_id')
                    ->constrained('parties')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('cheques', 'chequebook_id')) {
                $table->foreignId('chequebook_id')->nullable()->after('party_id')
                    ->constrained('chequebooks')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('cheques', 'accounting_document_id')) {
                $table->foreignId('accounting_document_id')->nullable()->after('payment_id')
                    ->constrained('accounting_documents')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('cheques', 'source_type')) {
                $table->string('source_type', 120)->nullable()->after('accounting_document_id');
            }
            if (! Schema::hasColumn('cheques', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('cheques', 'meta_json')) {
                $table->text('meta_json')->nullable()->after('notes');
            }
        });

        Schema::table('cheques', function (Blueprint $table): void {
            $table->index(['source_type', 'source_id'], 'cheques_source_idx');
            $table->index(['cheque_type', 'status', 'due_date'], 'cheques_type_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table): void {
            if (Schema::hasColumn('cheques', 'meta_json')) {
                $table->dropColumn('meta_json');
            }
            if (Schema::hasColumn('cheques', 'source_id')) {
                $table->dropColumn('source_id');
            }
            if (Schema::hasColumn('cheques', 'source_type')) {
                $table->dropColumn('source_type');
            }
            if (Schema::hasColumn('cheques', 'accounting_document_id')) {
                $table->dropConstrainedForeignId('accounting_document_id');
            }
            if (Schema::hasColumn('cheques', 'chequebook_id')) {
                $table->dropConstrainedForeignId('chequebook_id');
            }
            if (Schema::hasColumn('cheques', 'party_id')) {
                $table->dropConstrainedForeignId('party_id');
            }
        });
    }
};

