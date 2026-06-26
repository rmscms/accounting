<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Services\FiscalYearCloseOrchestrationService;

class FiscalYearCloseWizardController extends AccountingAdminController
{
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    public function show(int $fiscal_year, FiscalYearCloseOrchestrationService $orchestration)
    {
        $this->setTitle(trans('accounting::accounting.page_titles.fiscal_year_close_wizard'));
        $this->title(trans('accounting::accounting.page_titles.fiscal_year_close_wizard'));

        $context = $orchestration->getWizardContext($fiscal_year);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('fiscal_year_close_wizard')
            ->withCss('vendor/accounting/admin/css/fiscal-year-close-wizard.css', true)
            ->withJs('vendor/accounting/admin/js/fiscal-year-close-wizard.js', true)
            ->withVariables([
                'fiscalYear' => $context['fiscal_year'],
                'draftDocuments' => $context['draft_documents'],
                'isCurrent' => $context['is_current'],
                'canFullClose' => $context['can_full_close'],
                'fullCloseBlockReason' => $context['full_close_block_reason'],
                'nextYearCandidates' => $context['next_year_candidates'],
                'executeRoute' => route('admin.accounting.fiscal_years.close_wizard.execute', ['fiscal_year' => $fiscal_year]),
                'precheckRoute' => route('admin.accounting.fiscal_years.close_wizard.precheck', ['fiscal_year' => $fiscal_year]),
                'previewRoute' => route('admin.accounting.fiscal_years.close_wizard.preview', ['fiscal_year' => $fiscal_year]),
                'executeStepRoute' => route('admin.accounting.fiscal_years.close_wizard.execute_step', ['fiscal_year' => $fiscal_year]),
                'postcheckRoute' => route('admin.accounting.fiscal_years.close_wizard.postcheck', ['fiscal_year' => $fiscal_year]),
                'openNextRoute' => route('admin.accounting.fiscal_years.close_wizard.open_next', ['fiscal_year' => $fiscal_year]),
                'backRoute' => route('admin.accounting.fiscal_years.index'),
            ]);

        return $this->view();
    }

    public function execute(
        Request $request,
        int $fiscal_year,
        FiscalYearCloseOrchestrationService $orchestration
    ): RedirectResponse {
        $validated = $request->validate([
            'close_mode' => 'required|in:full_entries',
            'next_fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'create_next' => 'sometimes|boolean',
        ]);

        $userId = (int) (auth()->guard('admin')->id() ?? 0);
        if ($userId === 0) {
            return redirect()
                ->route('admin.accounting.fiscal_years.close_wizard', ['fiscal_year' => $fiscal_year])
                ->with('error', trans('accounting::accounting.fiscal_year_close.wizard.errors.not_authenticated'));
        }

        $nextId = isset($validated['next_fiscal_year_id']) ? (int) $validated['next_fiscal_year_id'] : null;
        $createNext = (bool) ($validated['create_next'] ?? false);

        try {
            $orchestration->closeWithClosingEntries($fiscal_year, $userId, null, false, true);
            $orchestration->openNextYearAndCreateOpening($fiscal_year, $userId, $nextId ?: null, $createNext);
            $message = trans('accounting::accounting.fiscal_year_close.wizard.success_full');
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.accounting.fiscal_years.close_wizard', ['fiscal_year' => $fiscal_year])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.accounting.fiscal_years.index')
            ->with('success', $message);
    }

    public function precheck(
        Request $request,
        int $fiscal_year,
        FiscalYearCloseOrchestrationService $orchestration
    ): JsonResponse {
        return $this->runWizardJson($request, static function () use ($orchestration, $fiscal_year): array {
            return $orchestration->runPrecheck($fiscal_year);
        });
    }

    public function preview(
        Request $request,
        int $fiscal_year,
        FiscalYearCloseOrchestrationService $orchestration
    ): JsonResponse {
        return $this->runWizardJson($request, static function () use ($orchestration, $fiscal_year): array {
            return $orchestration->buildPreview($fiscal_year);
        });
    }

    public function executeStep(
        Request $request,
        int $fiscal_year,
        FiscalYearCloseOrchestrationService $orchestration
    ): JsonResponse {
        $request->validate([
            'close_mode' => 'nullable|in:full_entries',
        ]);

        return $this->runWizardJson($request, function () use ($orchestration, $fiscal_year): array {
            $userId = $this->resolveAdminUserId();
            return $orchestration->executeCloseStep($fiscal_year, $userId);
        });
    }

    public function postcheck(
        Request $request,
        int $fiscal_year,
        FiscalYearCloseOrchestrationService $orchestration
    ): JsonResponse {
        return $this->runWizardJson($request, static function () use ($orchestration, $fiscal_year): array {
            return $orchestration->runPostcheck($fiscal_year);
        });
    }

    public function openNext(
        Request $request,
        int $fiscal_year,
        FiscalYearCloseOrchestrationService $orchestration
    ): JsonResponse {
        $validated = $request->validate([
            'next_fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'create_next' => 'sometimes|boolean',
        ]);

        return $this->runWizardJson($request, function () use ($orchestration, $fiscal_year, $validated): array {
            $userId = $this->resolveAdminUserId();
            $nextId = isset($validated['next_fiscal_year_id']) ? (int) $validated['next_fiscal_year_id'] : null;
            $createNext = (bool) ($validated['create_next'] ?? false);

            return $orchestration->openNextYearAndCreateOpening($fiscal_year, $userId, $nextId, $createNext);
        });
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    protected function runWizardJson(Request $_request, callable $callback): JsonResponse
    {
        try {
            $payload = $callback();

            return response()->json([
                'ok' => true,
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    protected function resolveAdminUserId(): int
    {
        $userId = (int) (auth()->guard('admin')->id() ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException(trans('accounting::accounting.fiscal_year_close.wizard.errors.not_authenticated'));
        }

        return $userId;
    }
}
