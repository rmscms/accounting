<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks');
            $table->foreignId('gl_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('statement_date');
            $table->decimal('book_balance', 20, 4)->default(0);
            $table->decimal('bank_statement_balance', 20, 4)->default(0);
            $table->decimal('adjusted_book_balance', 20, 4)->default(0);
            $table->decimal('adjusted_bank_balance', 20, 4)->default(0);
            $table->decimal('difference_amount', 20, 4)->default(0);
            $table->string('status', 32)->default('draft');
            $table->boolean('is_balanced')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bank_id', 'statement_date']);
            $table->index(['status', 'is_balanced']);
        });

        Schema::create('bank_reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->string('item_type', 40);
            $table->decimal('amount', 20, 4)->default(0);
            $table->string('effect_side', 16)->default('bank');
            $table->decimal('effect_sign', 8, 2)->default(1);
            $table->string('state', 24)->default('draft');
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->date('reference_date')->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['bank_reconciliation_id', 'item_type']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('bank_reconciliation_journal_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_reconciliation_item_id')->constrained('bank_reconciliation_items')->cascadeOnDelete();
            $table->json('journal_payload_json')->nullable();
            $table->foreignId('manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['manual_journal_id', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_journal_drafts');
        Schema::dropIfExists('bank_reconciliation_items');
        Schema::dropIfExists('bank_reconciliations');
    }
};

