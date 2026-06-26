<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policy_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('code', 64)->unique();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('standard_month_days')->default(30);
            $table->unsignedInteger('daily_work_minutes')->default(440);
            $table->decimal('overtime_rate_multiplier', 8, 4)->default(1.4);
            $table->decimal('taxable_allowance', 20, 4)->default(0);
            $table->json('policy_json')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_periods', function (Blueprint $table): void {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_key', 32)->unique();
            $table->enum('status', ['draft', 'submitted', 'supervisor_approved', 'hr_approved', 'locked'])->default('draft');
            $table->foreignId('policy_profile_id')->nullable()->constrained('attendance_policy_profiles')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('supervisor_approved_at')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('lock_reason')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->unsignedBigInteger('supervisor_approved_by_user_id')->nullable();
            $table->unsignedBigInteger('hr_approved_by_user_id')->nullable();
            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            $table->unsignedBigInteger('unlocked_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
            $table->index('status');
        });

        Schema::create('attendance_period_locks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_period_id')->constrained('attendance_periods')->cascadeOnDelete();
            $table->enum('action', ['lock', 'unlock']);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('acted_by_user_id')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['attendance_period_id', 'action']);
        });

        Schema::create('attendance_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->enum('source_type', ['device_api', 'csv', 'manual']);
            $table->string('source_reference', 191)->nullable();
            $table->string('import_hash', 64)->unique();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->json('report_json')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'created_at']);
        });

        Schema::create('attendance_raw_punches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('attendance_import_batches')->nullOnDelete();
            $table->dateTime('punch_at');
            $table->enum('direction', ['in', 'out'])->nullable();
            $table->string('device_id', 120)->nullable();
            $table->string('source_reference', 191)->nullable();
            $table->string('dedupe_hash', 64);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique('dedupe_hash');
            $table->index(['employee_id', 'punch_at']);
        });

        Schema::create('attendance_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_period_id')->constrained('attendance_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('policy_profile_id')->nullable()->constrained('attendance_policy_profiles')->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->unsignedInteger('leave_minutes')->default(0);
            $table->unsignedInteger('absence_minutes')->default(0);
            $table->decimal('worked_day_fraction', 8, 4)->default(0);
            $table->decimal('payable_day_fraction', 8, 4)->default(0);
            $table->enum('status', ['draft', 'submitted', 'supervisor_approved', 'hr_approved', 'locked'])->default('draft');
            $table->boolean('is_manual_override')->default(false);
            $table->boolean('is_termination_final_day')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('supervisor_approved_at')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->unsignedBigInteger('supervisor_approved_by_user_id')->nullable();
            $table->unsignedBigInteger('hr_approved_by_user_id')->nullable();
            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            $table->json('anomaly_flags_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['attendance_period_id', 'status']);
        });

        Schema::create('attendance_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 120);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 80);
            $table->text('reason')->nullable();
            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->unsignedBigInteger('acted_by_user_id')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'acted_at']);
        });

        Schema::create('payroll_attendance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('payroll_run_line_id')->constrained('payroll_run_lines')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('attendance_period_id')->nullable()->constrained('attendance_periods')->nullOnDelete();
            $table->foreignId('policy_profile_id')->nullable()->constrained('attendance_policy_profiles')->nullOnDelete();
            $table->decimal('planned_days', 10, 4)->default(0);
            $table->decimal('worked_days', 10, 4)->default(0);
            $table->decimal('payable_days', 10, 4)->default(0);
            $table->decimal('proration_factor', 10, 6)->default(1);
            $table->decimal('prorated_base_salary', 20, 4)->default(0);
            $table->decimal('prorated_benefits', 20, 4)->default(0);
            $table->decimal('prorated_insurable_base', 20, 4)->default(0);
            $table->decimal('prorated_taxable_base', 20, 4)->default(0);
            $table->json('source_breakdown_json')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_line_id']);
            $table->index(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_attendance_snapshots');
        Schema::dropIfExists('attendance_audit_logs');
        Schema::dropIfExists('attendance_daily');
        Schema::dropIfExists('attendance_raw_punches');
        Schema::dropIfExists('attendance_import_batches');
        Schema::dropIfExists('attendance_period_locks');
        Schema::dropIfExists('attendance_periods');
        Schema::dropIfExists('attendance_policy_profiles');
    }
};
