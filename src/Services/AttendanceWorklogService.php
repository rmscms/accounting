<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\AttendanceAuditLog;
use RMS\Accounting\Models\AttendanceDaily;
use RMS\Accounting\Models\AttendanceImportBatch;
use RMS\Accounting\Models\AttendancePeriod;
use RMS\Accounting\Models\AttendancePeriodLock;
use RMS\Accounting\Models\AttendancePolicyProfile;
use RMS\Accounting\Models\AttendanceRawPunch;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\PayrollAttendanceSnapshot;
use RMS\Accounting\Models\PayrollRun;
use RMS\Accounting\Models\PayrollRunLine;
use RMS\Accounting\Support\AttendanceProration;
use RMS\Core\Models\Setting;

class AttendanceWorklogService
{
    protected const DEFAULT_ATTENDANCE_TIMEZONE = 'Asia/Tehran';

    public function featureEnabled(): bool
    {
        return filter_var(
            Setting::get('accounting.payroll.attendance.feature_enabled', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function resolveOrCreatePeriod(string $periodStart, string $periodEnd): AttendancePeriod
    {
        $start = Carbon::parse($periodStart)->format('Y-m-d');
        $end = Carbon::parse($periodEnd)->format('Y-m-d');
        $key = Carbon::parse($end)->format('Y-m');

        $period = AttendancePeriod::query()->firstOrCreate(
            ['period_key' => $key],
            [
                'period_start' => $start,
                'period_end' => $end,
                'status' => AttendancePeriod::STATUS_DRAFT,
                'policy_profile_id' => $this->defaultPolicy()->id,
            ]
        );

        if ((string) $period->period_start?->format('Y-m-d') !== $start || (string) $period->period_end?->format('Y-m-d') !== $end) {
            $this->assertPeriodMutable($period);
            $period->update(['period_start' => $start, 'period_end' => $end]);
        }

        return $period->fresh();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function upsertDaily(array $payload, ?int $actorUserId = null): AttendanceDaily
    {
        $workDate = Carbon::parse((string) $payload['work_date'])->format('Y-m-d');
        $period = $this->resolveOrCreatePeriod(
            (string) ($payload['period_start'] ?? Carbon::parse($workDate)->startOfMonth()->format('Y-m-d')),
            (string) ($payload['period_end'] ?? Carbon::parse($workDate)->endOfMonth()->format('Y-m-d'))
        );
        $this->assertPeriodMutable($period);

        $policy = $this->policyForEmployee((int) ($payload['employee_id'] ?? 0), $period);
        $plannedMinutes = max(0, (int) ($payload['planned_minutes'] ?? $policy->daily_work_minutes));
        $workedMinutes = max(0, (int) ($payload['worked_minutes'] ?? 0));
        $leaveMinutes = max(0, (int) ($payload['leave_minutes'] ?? 0));
        $absenceMinutes = max(0, (int) ($payload['absence_minutes'] ?? 0));
        $payableMinutes = max(0, $workedMinutes + $leaveMinutes - $absenceMinutes);
        $workedFraction = $plannedMinutes > 0 ? min(1.5, round($workedMinutes / $plannedMinutes, 4)) : 0.0;
        $payableFraction = $plannedMinutes > 0 ? min(1.5, round($payableMinutes / $plannedMinutes, 4)) : 0.0;

        /** @var AttendanceDaily $daily */
        $daily = AttendanceDaily::query()->updateOrCreate(
            [
                'employee_id' => (int) $payload['employee_id'],
                'work_date' => $workDate,
            ],
            [
                'attendance_period_id' => $period->id,
                'policy_profile_id' => $policy->id,
                'planned_minutes' => $plannedMinutes,
                'worked_minutes' => $workedMinutes,
                'overtime_minutes' => max(0, (int) ($payload['overtime_minutes'] ?? 0)),
                'late_minutes' => max(0, (int) ($payload['late_minutes'] ?? 0)),
                'undertime_minutes' => max(0, (int) ($payload['undertime_minutes'] ?? 0)),
                'leave_minutes' => $leaveMinutes,
                'absence_minutes' => $absenceMinutes,
                'worked_day_fraction' => $workedFraction,
                'payable_day_fraction' => $payableFraction,
                'status' => AttendancePeriod::STATUS_DRAFT,
                'is_manual_override' => filter_var($payload['is_manual_override'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'is_termination_final_day' => filter_var($payload['is_termination_final_day'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'anomaly_flags_json' => $payload['anomaly_flags_json'] ?? null,
                'meta_json' => $payload['meta_json'] ?? null,
                'notes' => (string) ($payload['notes'] ?? ''),
            ]
        );

        $this->logAudit('attendance_daily', (int) $daily->id, 'upsert_daily', null, null, $daily->toArray(), $actorUserId);

        return $daily->fresh();
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    public function importDevicePunchRows(array $rows, ?string $sourceReference = null, ?int $actorUserId = null): array
    {
        $hash = hash('sha256', json_encode([$rows, $sourceReference], JSON_UNESCAPED_UNICODE));
        $existingBatch = AttendanceImportBatch::query()->where('import_hash', $hash)->first();
        if ($existingBatch instanceof AttendanceImportBatch) {
            return [
                'batch_id' => (int) $existingBatch->id,
                'rows_total' => (int) $existingBatch->rows_total,
                'rows_imported' => 0,
                'rows_skipped' => (int) $existingBatch->rows_total,
                'rows_failed' => 0,
                'is_duplicate' => true,
            ];
        }

        return DB::transaction(function () use ($rows, $sourceReference, $actorUserId, $hash): array {
            $batch = AttendanceImportBatch::query()->create([
                'source_type' => 'device_api',
                'source_reference' => $sourceReference,
                'import_hash' => $hash,
                'rows_total' => count($rows),
                'created_by_user_id' => $actorUserId,
            ]);

            $imported = 0;
            $skipped = 0;
            $failed = 0;
            $affectedEmployeeIds = [];
            $minDate = null;
            $maxDate = null;
            foreach ($rows as $row) {
                try {
                    $employeeId = (int) ($row['employee_id'] ?? 0);
                    $punchAtCarbon = $this->normalizePunchAt(
                        (string) ($row['punch_at'] ?? ''),
                        isset($row['timezone']) ? (string) $row['timezone'] : null
                    );
                    $punchAt = $punchAtCarbon->format('Y-m-d H:i:s');
                    if ($employeeId <= 0 || ! Employee::query()->whereKey($employeeId)->exists()) {
                        $failed++;
                        continue;
                    }
                    $dedupeHash = hash('sha256', implode('|', [
                        $employeeId,
                        $punchAt,
                        (string) ($row['direction'] ?? ''),
                        (string) ($row['device_id'] ?? ''),
                        (string) ($row['source_reference'] ?? ''),
                    ]));
                    $rawPunch = AttendanceRawPunch::query()->firstOrCreate(
                        ['dedupe_hash' => $dedupeHash],
                        [
                            'employee_id' => $employeeId,
                            'import_batch_id' => $batch->id,
                            'punch_at' => $punchAt,
                            'direction' => in_array(($row['direction'] ?? null), ['in', 'out'], true) ? (string) $row['direction'] : null,
                            'device_id' => (string) ($row['device_id'] ?? ''),
                            'source_reference' => (string) ($row['source_reference'] ?? ''),
                            'meta_json' => $row,
                        ]
                    );
                    if (! $rawPunch->wasRecentlyCreated) {
                        $skipped++;
                        continue;
                    }
                    $imported++;
                    $affectedEmployeeIds[] = $employeeId;
                    $workDate = $punchAtCarbon->toDateString();
                    $minDate = $minDate === null || strcmp($workDate, $minDate) < 0 ? $workDate : $minDate;
                    $maxDate = $maxDate === null || strcmp($workDate, $maxDate) > 0 ? $workDate : $maxDate;
                } catch (\Throwable) {
                    $failed++;
                }
            }

            $periodStart = $minDate !== null ? Carbon::parse($minDate)->startOfMonth()->toDateString() : null;
            $periodEnd = $maxDate !== null ? Carbon::parse($maxDate)->endOfMonth()->toDateString() : null;

            if ($imported > 0 && $periodStart !== null && $periodEnd !== null && $affectedEmployeeIds !== []) {
                $this->rebuildDailyFromRawPunches(
                    array_values(array_unique(array_map(static fn ($id): int => (int) $id, $affectedEmployeeIds))),
                    $periodStart,
                    $periodEnd,
                    $actorUserId
                );
            }

            $batch->update([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'rows_imported' => $imported,
                'rows_skipped' => $skipped,
                'rows_failed' => $failed,
                'report_json' => [
                    'source' => 'device_api',
                    'timezone' => $this->attendanceTimezone(),
                    'employees' => count(array_unique($affectedEmployeeIds)),
                ],
            ]);

            return [
                'batch_id' => (int) $batch->id,
                'rows_total' => count($rows),
                'rows_imported' => $imported,
                'rows_skipped' => $skipped,
                'rows_failed' => $failed,
                'is_duplicate' => false,
            ];
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function importDailyCsv(UploadedFile $file, string $periodStart, string $periodEnd, ?int $actorUserId = null): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        $hash = hash('sha256', $content . '|' . $periodStart . '|' . $periodEnd);
        $existingBatch = AttendanceImportBatch::query()->where('import_hash', $hash)->first();
        if ($existingBatch instanceof AttendanceImportBatch) {
            return [
                'batch_id' => (int) $existingBatch->id,
                'rows_total' => (int) $existingBatch->rows_total,
                'rows_imported' => 0,
                'rows_skipped' => (int) $existingBatch->rows_total,
                'rows_failed' => 0,
                'is_duplicate' => true,
            ];
        }

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.csv_unreadable'));
        }

        $header = null;
        $rows = [];
        while (($line = fgetcsv($stream)) !== false) {
            if ($header === null) {
                $header = $line;
                continue;
            }
            $rows[] = array_combine((array) $header, $line) ?: [];
        }
        fclose($stream);

        $batch = AttendanceImportBatch::query()->create([
            'source_type' => 'csv',
            'source_reference' => $file->getClientOriginalName(),
            'import_hash' => $hash,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'rows_total' => count($rows),
            'created_by_user_id' => $actorUserId,
        ]);

        $imported = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                $this->upsertDaily([
                    'employee_id' => (int) ($row['employee_id'] ?? 0),
                    'work_date' => (string) ($row['work_date'] ?? ''),
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'planned_minutes' => (int) ($row['planned_minutes'] ?? 0),
                    'worked_minutes' => (int) ($row['worked_minutes'] ?? 0),
                    'overtime_minutes' => (int) ($row['overtime_minutes'] ?? 0),
                    'late_minutes' => (int) ($row['late_minutes'] ?? 0),
                    'undertime_minutes' => (int) ($row['undertime_minutes'] ?? 0),
                    'leave_minutes' => (int) ($row['leave_minutes'] ?? 0),
                    'absence_minutes' => (int) ($row['absence_minutes'] ?? 0),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'meta_json' => ['source' => 'csv', 'batch_id' => $batch->id],
                ], $actorUserId);
                $imported++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $skipped = max(0, count($rows) - $imported - $failed);
        $batch->update([
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
            'rows_failed' => $failed,
            'report_json' => ['source' => 'csv', 'file' => $file->getClientOriginalName()],
        ]);

        return [
            'batch_id' => (int) $batch->id,
            'rows_total' => count($rows),
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
            'rows_failed' => $failed,
            'is_duplicate' => false,
        ];
    }

    /**
     * @param array<int,int> $employeeIds
     */
    public function rebuildDailyFromRawPunches(array $employeeIds, string $periodStart, string $periodEnd, ?int $actorUserId = null): void
    {
        if ($employeeIds === []) {
            return;
        }

        $normalizedPeriodStart = Carbon::parse($periodStart)->format('Y-m-d');
        $normalizedPeriodEnd = Carbon::parse($periodEnd)->format('Y-m-d');

        $rawPunches = AttendanceRawPunch::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('punch_at', '>=', $normalizedPeriodStart)
            ->whereDate('punch_at', '<=', $normalizedPeriodEnd)
            ->orderBy('employee_id')
            ->orderBy('punch_at')
            ->get(['employee_id', 'punch_at', 'direction', 'source_reference', 'device_id']);

        $grouped = [];
        foreach ($rawPunches as $punch) {
            $normalized = $this->normalizePunchAt((string) $punch->punch_at);
            $workDate = $normalized->toDateString();
            $grouped[(int) $punch->employee_id][$workDate][] = [
                'at' => $normalized,
                'direction' => $punch->direction,
                'source_reference' => (string) ($punch->source_reference ?? ''),
                'device_id' => (string) ($punch->device_id ?? ''),
            ];
        }

        foreach ($grouped as $employeeId => $dailyRows) {
            foreach ($dailyRows as $workDate => $punches) {
                $calculated = $this->calculateDailyFromPunches($punches, $workDate);
                $this->upsertDaily([
                    'employee_id' => (int) $employeeId,
                    'work_date' => $workDate,
                    'period_start' => $normalizedPeriodStart,
                    'period_end' => $normalizedPeriodEnd,
                    'planned_minutes' => $calculated['planned_minutes'],
                    'worked_minutes' => $calculated['worked_minutes'],
                    'overtime_minutes' => $calculated['overtime_minutes'],
                    'late_minutes' => $calculated['late_minutes'],
                    'undertime_minutes' => $calculated['undertime_minutes'],
                    'leave_minutes' => 0,
                    'absence_minutes' => $calculated['absence_minutes'],
                    'is_manual_override' => false,
                    'anomaly_flags_json' => $calculated['anomaly_flags'],
                    'meta_json' => ['source' => 'device_api', 'segments' => $calculated['segments']],
                    'notes' => '',
                ], $actorUserId);
            }
        }
    }

    public function submitPeriod(AttendancePeriod $period, ?int $actorUserId = null): AttendancePeriod
    {
        $this->assertPeriodMutable($period);
        $period->update([
            'status' => AttendancePeriod::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_user_id' => $actorUserId,
        ]);
        AttendanceDaily::query()
            ->where('attendance_period_id', $period->id)
            ->update([
                'status' => AttendancePeriod::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by_user_id' => $actorUserId,
            ]);
        $this->logAudit('attendance_period', (int) $period->id, 'submit_period', null, null, $period->fresh()->toArray(), $actorUserId);

        return $period->fresh();
    }

    public function supervisorApprovePeriod(AttendancePeriod $period, ?int $actorUserId = null): AttendancePeriod
    {
        if (! in_array($period->status, [AttendancePeriod::STATUS_SUBMITTED, AttendancePeriod::STATUS_SUPERVISOR_APPROVED], true)) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.invalid_approval_state'));
        }
        $period->update([
            'status' => AttendancePeriod::STATUS_SUPERVISOR_APPROVED,
            'supervisor_approved_at' => now(),
            'supervisor_approved_by_user_id' => $actorUserId,
        ]);
        AttendanceDaily::query()
            ->where('attendance_period_id', $period->id)
            ->update([
                'status' => AttendancePeriod::STATUS_SUPERVISOR_APPROVED,
                'supervisor_approved_at' => now(),
                'supervisor_approved_by_user_id' => $actorUserId,
            ]);
        $this->logAudit('attendance_period', (int) $period->id, 'supervisor_approve', null, null, $period->fresh()->toArray(), $actorUserId);

        return $period->fresh();
    }

    public function hrApprovePeriod(AttendancePeriod $period, ?int $actorUserId = null): AttendancePeriod
    {
        if (! in_array($period->status, [AttendancePeriod::STATUS_SUPERVISOR_APPROVED, AttendancePeriod::STATUS_HR_APPROVED], true)) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.invalid_approval_state'));
        }
        $period->update([
            'status' => AttendancePeriod::STATUS_HR_APPROVED,
            'hr_approved_at' => now(),
            'hr_approved_by_user_id' => $actorUserId,
        ]);
        AttendanceDaily::query()
            ->where('attendance_period_id', $period->id)
            ->update([
                'status' => AttendancePeriod::STATUS_HR_APPROVED,
                'hr_approved_at' => now(),
                'hr_approved_by_user_id' => $actorUserId,
            ]);
        $this->logAudit('attendance_period', (int) $period->id, 'hr_approve', null, null, $period->fresh()->toArray(), $actorUserId);

        return $period->fresh();
    }

    public function lockPeriod(AttendancePeriod $period, string $reason, ?int $actorUserId = null): AttendancePeriod
    {
        if (! in_array($period->status, [AttendancePeriod::STATUS_HR_APPROVED, AttendancePeriod::STATUS_LOCKED], true)) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.period_requires_hr_approval'));
        }

        $period->update([
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
            'locked_by_user_id' => $actorUserId,
            'lock_reason' => $reason,
        ]);
        AttendanceDaily::query()
            ->where('attendance_period_id', $period->id)
            ->update([
                'status' => AttendancePeriod::STATUS_LOCKED,
                'locked_at' => now(),
                'locked_by_user_id' => $actorUserId,
            ]);
        AttendancePeriodLock::query()->create([
            'attendance_period_id' => $period->id,
            'action' => 'lock',
            'reason' => $reason,
            'acted_by_user_id' => $actorUserId,
            'acted_at' => now(),
        ]);
        $this->logAudit('attendance_period', (int) $period->id, 'lock', $reason, null, $period->fresh()->toArray(), $actorUserId);

        return $period->fresh();
    }

    public function unlockPeriod(AttendancePeriod $period, string $reason, ?int $actorUserId = null): AttendancePeriod
    {
        if ($period->status !== AttendancePeriod::STATUS_LOCKED) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.period_not_locked'));
        }

        $period->update([
            'status' => AttendancePeriod::STATUS_HR_APPROVED,
            'unlock_reason' => $reason,
            'unlocked_by_user_id' => $actorUserId,
        ]);
        AttendanceDaily::query()
            ->where('attendance_period_id', $period->id)
            ->update([
                'status' => AttendancePeriod::STATUS_HR_APPROVED,
            ]);
        AttendancePeriodLock::query()->create([
            'attendance_period_id' => $period->id,
            'action' => 'unlock',
            'reason' => $reason,
            'acted_by_user_id' => $actorUserId,
            'acted_at' => now(),
        ]);
        $this->logAudit('attendance_period', (int) $period->id, 'unlock', $reason, null, $period->fresh()->toArray(), $actorUserId);

        return $period->fresh();
    }

    /**
     * @param array<int,int> $employeeIds
     */
    public function assertPayrollAllowed(string $periodStart, string $periodEnd, array $employeeIds): void
    {
        if (! $this->featureEnabled()) {
            return;
        }
        $period = $this->resolveOrCreatePeriod($periodStart, $periodEnd);
        if (! in_array($period->status, [AttendancePeriod::STATUS_HR_APPROVED, AttendancePeriod::STATUS_LOCKED], true)) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.period_requires_hr_approval'));
        }

        $missingEmployeeIds = [];
        foreach (array_unique($employeeIds) as $employeeId) {
            $count = AttendanceDaily::query()
                ->where('attendance_period_id', $period->id)
                ->where('employee_id', (int) $employeeId)
                ->whereIn('status', [AttendancePeriod::STATUS_HR_APPROVED, AttendancePeriod::STATUS_LOCKED])
                ->count();
            if ($count === 0) {
                $missingEmployeeIds[] = (int) $employeeId;
            }
        }
        if ($missingEmployeeIds !== []) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.missing_employee_approval', [
                'employees' => implode(', ', $missingEmployeeIds),
            ]));
        }
    }

    /**
     * @param array<string,mixed> $line
     * @return array<string,mixed>
     */
    public function applyProrationToLine(array $line, string $periodStart, string $periodEnd): array
    {
        if (! $this->featureEnabled()) {
            return $line;
        }

        $employeeId = (int) ($line['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return $line;
        }

        $summary = $this->employeeSummary($employeeId, $periodStart, $periodEnd);
        if ($summary === null || (float) $summary['standard_month_days'] <= 0) {
            return $line;
        }

        $prorated = AttendanceProration::prorate(
            (float) ($line['base_salary'] ?? 0),
            (float) ($line['benefits'] ?? 0),
            (float) $summary['payable_days'],
            (float) $summary['standard_month_days']
        );

        $line['base_salary'] = $prorated['base_salary'];
        $line['benefits'] = $prorated['benefits'];
        $line['_attendance_snapshot'] = [
            'attendance_period_id' => (int) $summary['period_id'],
            'policy_profile_id' => (int) $summary['policy_profile_id'],
            'planned_days' => (float) $summary['planned_days'],
            'worked_days' => (float) $summary['worked_days'],
            'payable_days' => (float) $summary['payable_days'],
            'proration_factor' => $prorated['factor'],
            'prorated_base_salary' => (float) $line['base_salary'],
            'prorated_benefits' => (float) $line['benefits'],
            'prorated_insurable_base' => $prorated['insurable_base'],
            'prorated_taxable_base' => $prorated['taxable_base'],
            'source_breakdown_json' => [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'termination_final_day' => (bool) $summary['termination_final_day'],
            ],
        ];

        return $line;
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    public function syncPayrollSnapshot(PayrollRun $run, PayrollRunLine $line, array $snapshot): void
    {
        PayrollAttendanceSnapshot::query()->updateOrCreate(
            ['payroll_run_line_id' => (int) $line->id],
            array_merge($snapshot, [
                'payroll_run_id' => (int) $run->id,
                'employee_id' => (int) $line->employee_id,
            ])
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function periodSummaryRows(AttendancePeriod $period): array
    {
        $rows = AttendanceDaily::query()
            ->with('employee')
            ->where('attendance_period_id', $period->id)
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->get();

        $summary = [];
        foreach ($rows as $row) {
            $employeeId = (int) $row->employee_id;
            if (! isset($summary[$employeeId])) {
                $summary[$employeeId] = [
                    'employee_name' => (string) ($row->employee?->name ?? ('#'.$employeeId)),
                    'planned_days' => 0.0,
                    'worked_days' => 0.0,
                    'payable_days' => 0.0,
                    'overtime_hours' => 0.0,
                    'leave_days' => 0.0,
                    'absence_days' => 0.0,
                ];
            }
            $summary[$employeeId]['planned_days'] += ((float) $row->planned_minutes > 0 ? 1.0 : 0.0);
            $summary[$employeeId]['worked_days'] += (float) $row->worked_day_fraction;
            $summary[$employeeId]['payable_days'] += (float) $row->payable_day_fraction;
            $summary[$employeeId]['overtime_hours'] += ((float) $row->overtime_minutes / 60.0);
            $summary[$employeeId]['leave_days'] += ((float) $row->leave_minutes / max(1.0, (float) $row->planned_minutes));
            $summary[$employeeId]['absence_days'] += ((float) $row->absence_minutes / max(1.0, (float) $row->planned_minutes));
        }

        return array_values($summary);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function reconciliationRows(string $periodStart, string $periodEnd): array
    {
        $runs = PayrollRun::query()
            ->with(['lines.employee', 'attendanceSnapshots'])
            ->whereDate('period_start', '>=', $periodStart)
            ->whereDate('period_end', '<=', $periodEnd)
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($runs as $run) {
            foreach ($run->lines as $line) {
                $snapshot = $run->attendanceSnapshots->firstWhere('payroll_run_line_id', $line->id);
                $rows[] = [
                    'run_number' => (string) $run->run_number,
                    'employee_name' => (string) ($line->employee?->name ?? ('#'.$line->employee_id)),
                    'payable_days' => (float) ($snapshot?->payable_days ?? 0),
                    'proration_factor' => (float) ($snapshot?->proration_factor ?? 1),
                    'base_salary' => (float) $line->base_salary,
                    'benefits' => (float) $line->benefits,
                    'net_salary' => (float) $line->net_salary,
                    'accrual_journal_id' => (int) ($run->accrual_manual_journal_id ?? 0),
                ];
            }
        }

        return $rows;
    }

    protected function defaultPolicy(): AttendancePolicyProfile
    {
        $policy = AttendancePolicyProfile::query()->where('is_default', true)->first();
        if ($policy instanceof AttendancePolicyProfile) {
            return $policy;
        }

        return AttendancePolicyProfile::query()->firstOrCreate(
            ['code' => 'iran-default'],
            [
                'title' => 'Iran Labor Default',
                'is_default' => true,
                'standard_month_days' => 30,
                'daily_work_minutes' => 440,
                'overtime_rate_multiplier' => 1.4,
                'taxable_allowance' => 0,
                'policy_json' => ['country' => 'IR', 'notes' => 'Base attendance profile'],
                'created_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]
        );
    }

    protected function policyForEmployee(int $employeeId, AttendancePeriod $period): AttendancePolicyProfile
    {
        $contract = EmployeeContractService::class;
        if (class_exists($contract)) {
            // Reserved for future contract-level policy binding.
        }

        return $period->policyProfile ?: $this->defaultPolicy();
    }

    protected function assertPeriodMutable(AttendancePeriod $period): void
    {
        if ($period->status === AttendancePeriod::STATUS_LOCKED) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.period_locked'));
        }
    }

    protected function attendanceTimezone(): string
    {
        $configured = (string) Setting::get('accounting.payroll.attendance.timezone', self::DEFAULT_ATTENDANCE_TIMEZONE);

        return trim($configured) !== '' ? $configured : self::DEFAULT_ATTENDANCE_TIMEZONE;
    }

    protected function normalizePunchAt(string $punchAt, ?string $inputTimezone = null): CarbonInterface
    {
        $fromTimezone = $inputTimezone !== null && trim($inputTimezone) !== ''
            ? trim($inputTimezone)
            : $this->attendanceTimezone();

        return Carbon::parse($punchAt, $fromTimezone)->setTimezone($this->attendanceTimezone());
    }

    /**
     * @param array<int,array<string,mixed>> $punches
     * @return array{
     *     planned_minutes:int,
     *     worked_minutes:int,
     *     overtime_minutes:int,
     *     late_minutes:int,
     *     undertime_minutes:int,
     *     absence_minutes:int,
     *     anomaly_flags:array<int,string>,
     *     segments:array<int,array{in:string,out:string,minutes:int}>
     * }
     */
    protected function calculateDailyFromPunches(array $punches, string $workDate): array
    {
        usort($punches, static function (array $a, array $b): int {
            /** @var CarbonInterface $left */
            $left = $a['at'];
            /** @var CarbonInterface $right */
            $right = $b['at'];

            return $left->getTimestamp() <=> $right->getTimestamp();
        });

        $anomalies = [];
        $segments = [];
        $plannedMinutes = (int) $this->defaultPolicy()->daily_work_minutes;
        $workedMinutes = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $absenceMinutes = 0;

        $expectedStart = Carbon::parse($workDate . ' 08:00:00', $this->attendanceTimezone());
        $clockIn = null;
        $knownDirections = true;
        foreach ($punches as $punch) {
            /** @var CarbonInterface $at */
            $at = $punch['at'];
            $direction = $punch['direction'];
            if (! in_array($direction, ['in', 'out'], true)) {
                $knownDirections = false;
            }
            if ($direction === 'in') {
                $clockIn = $at;
                if ($clockIn->greaterThan($expectedStart)) {
                    $lateMinutes = max($lateMinutes, $expectedStart->diffInMinutes($clockIn));
                }
                continue;
            }
            if ($direction === 'out' && $clockIn instanceof CarbonInterface && $at->greaterThan($clockIn)) {
                $minutes = $clockIn->diffInMinutes($at);
                $workedMinutes += $minutes;
                $segments[] = [
                    'in' => $clockIn->format('H:i:s'),
                    'out' => $at->format('H:i:s'),
                    'minutes' => $minutes,
                ];
                $clockIn = null;
            }
        }

        if ($clockIn instanceof CarbonInterface) {
            $anomalies[] = 'missing_checkout';
        }
        if (! $knownDirections) {
            $anomalies[] = 'unknown_direction';
        }
        if (count($punches) < 2) {
            $anomalies[] = 'single_punch';
        }

        if ($workedMinutes > ($plannedMinutes * 2)) {
            $anomalies[] = 'long_shift';
        }

        if ($workedMinutes < $plannedMinutes) {
            $undertimeMinutes = $plannedMinutes - $workedMinutes;
            $absenceMinutes = $undertimeMinutes;
        }

        return [
            'planned_minutes' => $plannedMinutes,
            'worked_minutes' => max(0, $workedMinutes),
            'overtime_minutes' => max(0, $workedMinutes - $plannedMinutes),
            'late_minutes' => max(0, $lateMinutes),
            'undertime_minutes' => max(0, $undertimeMinutes),
            'absence_minutes' => max(0, $absenceMinutes),
            'anomaly_flags' => array_values(array_unique($anomalies)),
            'segments' => $segments,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function employeeSummary(int $employeeId, string $periodStart, string $periodEnd): ?array
    {
        $period = AttendancePeriod::query()
            ->whereDate('period_start', '<=', $periodStart)
            ->whereDate('period_end', '>=', $periodEnd)
            ->whereIn('status', [AttendancePeriod::STATUS_HR_APPROVED, AttendancePeriod::STATUS_LOCKED])
            ->orderByDesc('id')
            ->first();

        if (! $period instanceof AttendancePeriod) {
            return null;
        }

        $rows = AttendanceDaily::query()
            ->where('attendance_period_id', $period->id)
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '>=', $periodStart)
            ->whereDate('work_date', '<=', $periodEnd)
            ->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $policyId = (int) ($rows->first()->policy_profile_id ?? ($period->policy_profile_id ?? $this->defaultPolicy()->id));
        $policy = AttendancePolicyProfile::query()->find($policyId) ?: $this->defaultPolicy();

        return [
            'period_id' => (int) $period->id,
            'policy_profile_id' => (int) $policy->id,
            'planned_days' => (float) $rows->sum(static fn (AttendanceDaily $row): float => $row->planned_minutes > 0 ? 1.0 : 0.0),
            'worked_days' => (float) $rows->sum('worked_day_fraction'),
            'payable_days' => (float) $rows->sum('payable_day_fraction'),
            'standard_month_days' => (int) $policy->standard_month_days,
            'termination_final_day' => $rows->contains(static fn (AttendanceDaily $row): bool => (bool) $row->is_termination_final_day),
        ];
    }

    /**
     * @param array<string,mixed>|null $oldValues
     * @param array<string,mixed>|null $newValues
     */
    protected function logAudit(
        string $entityType,
        int $entityId,
        string $action,
        ?string $reason = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $actorUserId = null
    ): void {
        AttendanceAuditLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'reason' => $reason,
            'old_values_json' => $oldValues,
            'new_values_json' => $newValues,
            'acted_by_user_id' => $actorUserId,
            'acted_at' => now(),
        ]);
    }
}
