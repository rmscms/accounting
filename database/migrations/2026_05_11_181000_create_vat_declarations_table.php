<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_declarations', function (Blueprint $table): void {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('fiscal_quarter');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_declaration_id')->nullable()->constrained('vat_declarations')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->json('snapshot_json')->nullable();
            $table->json('official_export_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
            $table->index(['fiscal_year', 'fiscal_quarter', 'version']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_declarations');
    }
};
