<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Models\AttendanceDaily;
use RMS\Accounting\Models\AttendancePeriod;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\AttendanceWorklogService;
use RMS\Accounting\Support\AuditActor;
use RMS\Accounting\Support\AccountingDateUi;

class AttendanceWorklogsController extends AccountingAdminController
{
    public function table(): string
    {
        return 'attendance_periods';
    }

    public function modelName(): string
    {
        return AttendancePeriod::class;
    }

    public function index(Request $request)
    {
        $periods = AttendancePeriod::query()
            ->orderByDesc('period_start')
            ->paginate(24);

        $defaultStart = (string) now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = (string) now()->endOfMonth()->format('Y-m-d');

        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        if (! in_array('confirm-modal', $plugins, true)) {
            $plugins[] = 'confirm-modal';
        }

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('attendance_worklogs.index')
            ->withPlugins($plugins)
            ->withVariables([
                'periods' => $periods,
                'featureEnabled' => app(AttendanceWorklogService::class)->featureEnabled(),
                'defaultPeriodStartDisplay' => AccountingDateUi::gregorianYmdToInputDisplay($defaultStart),
                'defaultPeriodEndDisplay' => AccountingDateUi::gregorianYmdToInputDisplay($defaultEnd),
            ]);

        return $this->view();
    }

    public function openPeriod(Request $request, AttendanceWorklogService $service): RedirectResponse
    {
        $validated = $request->validate([
            'period_start' => 'required|string|max:64',
            'period_end' => 'required|string|max:64',
        ]);

        $normalizer = app(AccountingDateInputNormalizer::class);
        $periodStart = $normalizer->normalizeFilterDateToGregorian((string) $validated['period_start']);
        $periodEnd = $normalizer->normalizeFilterDateToGregorian((string) $validated['period_end']);
        if ($periodStart === null || $periodEnd === null) {
            throw new \RuntimeException((string) trans('accounting::errors.invalid_date'));
        }

        if ($periodStart > $periodEnd) {
            throw new \InvalidArgumentException((string) trans('accounting::accounting.attendance.errors.invalid_period_range'));
        }

        $period = $service->resolveOrCreatePeriod($periodStart, $periodEnd);

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $period->id)
            ->with('success', trans('accounting::accounting.attendance.flash_period_opened'));
    }

    public function show(Request $request, int|string $period)
    {
        $model = AttendancePeriod::query()->with(['dailyRows.employee'])->findOrFail((int) $period);
        $summaryRows = app(AttendanceWorklogService::class)->periodSummaryRows($model);
        $employees = Employee::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $normalizer = app(AccountingDateInputNormalizer::class);

        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        if (! in_array('confirm-modal', $plugins, true)) {
            $plugins[] = 'confirm-modal';
        }
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('attendance_worklogs.show')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/attendance-worklogs.js', true)
            ->withVariables([
                'period' => $model,
                'summaryRows' => $summaryRows,
                'employees' => $employees,
                'periodStartDisplay' => AccountingDateUi::gregorianYmdToInputDisplay((string) $model->period_start?->format('Y-m-d')),
                'periodEndDisplay' => AccountingDateUi::gregorianYmdToInputDisplay((string) $model->period_end?->format('Y-m-d')),
                'dateNormalizer' => $normalizer,
            ]);

        return $this->view();
    }

    public function upsertDaily(Request $request, AttendanceWorklogService $service): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:attendance_periods,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'work_date' => 'required|string|max:64',
            'planned_minutes' => 'nullable|integer|min:0',
            'worked_minutes' => 'nullable|integer|min:0',
            'overtime_minutes' => 'nullable|integer|min:0',
            'late_minutes' => 'nullable|integer|min:0',
            'undertime_minutes' => 'nullable|integer|min:0',
            'leave_minutes' => 'nullable|integer|min:0',
            'absence_minutes' => 'nullable|integer|min:0',
            'is_termination_final_day' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $period = AttendancePeriod::query()->findOrFail((int) $validated['period_id']);
        $normalizedWorkDate = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian((string) $validated['work_date']);
        if ($normalizedWorkDate === null) {
            throw new \RuntimeException((string) trans('accounting::errors.invalid_date'));
        }

        $service->upsertDaily([
            'employee_id' => (int) $validated['employee_id'],
            'work_date' => $normalizedWorkDate,
            'period_start' => (string) $period->period_start?->format('Y-m-d'),
            'period_end' => (string) $period->period_end?->format('Y-m-d'),
            'planned_minutes' => (int) ($validated['planned_minutes'] ?? 0),
            'worked_minutes' => (int) ($validated['worked_minutes'] ?? 0),
            'overtime_minutes' => (int) ($validated['overtime_minutes'] ?? 0),
            'late_minutes' => (int) ($validated['late_minutes'] ?? 0),
            'undertime_minutes' => (int) ($validated['undertime_minutes'] ?? 0),
            'leave_minutes' => (int) ($validated['leave_minutes'] ?? 0),
            'absence_minutes' => (int) ($validated['absence_minutes'] ?? 0),
            'is_termination_final_day' => filter_var($validated['is_termination_final_day'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'notes' => (string) ($validated['notes'] ?? ''),
        ], AuditActor::userId());

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $period->id)
            ->with('success', trans('accounting::accounting.attendance.flash_daily_saved'));
    }

    public function importCsv(Request $request, AttendanceWorklogService $service): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:attendance_periods,id',
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);
        $period = AttendancePeriod::query()->findOrFail((int) $validated['period_id']);
        $service->importDailyCsv(
            $request->file('csv_file'),
            (string) $period->period_start?->format('Y-m-d'),
            (string) $period->period_end?->format('Y-m-d'),
            AuditActor::userId()
        );

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $period->id)
            ->with('success', trans('accounting::accounting.attendance.flash_csv_imported'));
    }

    public function importDevice(Request $request, AttendanceWorklogService $service): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:attendance_periods,id',
            'rows_json' => 'required|string',
        ]);
        $period = AttendancePeriod::query()->findOrFail((int) $validated['period_id']);
        $rows = json_decode((string) $validated['rows_json'], true);
        if (! is_array($rows)) {
            throw new \RuntimeException((string) trans('accounting::accounting.attendance.errors.invalid_rows_json'));
        }
        $service->importDevicePunchRows($rows, 'manual-json-import', AuditActor::userId());

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $period->id)
            ->with('success', trans('accounting::accounting.attendance.flash_device_imported'));
    }

    public function submit(Request $request, int|string $period, AttendanceWorklogService $service): RedirectResponse
    {
        $model = AttendancePeriod::query()->findOrFail((int) $period);
        $service->submitPeriod($model, AuditActor::userId());

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $model->id)
            ->with('success', trans('accounting::accounting.attendance.flash_submitted'));
    }

    public function supervisorApprove(Request $request, int|string $period, AttendanceWorklogService $service): RedirectResponse
    {
        $model = AttendancePeriod::query()->findOrFail((int) $period);
        $service->supervisorApprovePeriod($model, AuditActor::userId());

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $model->id)
            ->with('success', trans('accounting::accounting.attendance.flash_supervisor_approved'));
    }

    public function hrApprove(Request $request, int|string $period, AttendanceWorklogService $service): RedirectResponse
    {
        $model = AttendancePeriod::query()->findOrFail((int) $period);
        $service->hrApprovePeriod($model, AuditActor::userId());

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $model->id)
            ->with('success', trans('accounting::accounting.attendance.flash_hr_approved'));
    }

    public function lock(Request $request, int|string $period, AttendanceWorklogService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);
        $model = AttendancePeriod::query()->findOrFail((int) $period);
        $service->lockPeriod($model, (string) $validated['reason'], AuditActor::userId());

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $model->id)
            ->with('success', trans('accounting::accounting.attendance.flash_locked'));
    }

    public function unlock(Request $request, int|string $period, AttendanceWorklogService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);
        $model = AttendancePeriod::query()->findOrFail((int) $period);
        $service->unlockPeriod($model, (string) $validated['reason'], AuditActor::userId());

        return redirect()
            ->route('admin.accounting.attendance-worklogs.show', $model->id)
            ->with('success', trans('accounting::accounting.attendance.flash_unlocked'));
    }
}
