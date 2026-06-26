<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\InventoryAdjustment;
use RMS\Accounting\Services\InventoryAdjustmentService;
use RMS\Accounting\Support\AccountingDateUi;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class InventoryAdjustmentsController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    protected InventoryAdjustmentService $adjustmentService;

    public function __construct(Filesystem $filesystem, InventoryAdjustmentService $adjustmentService)
    {
        parent::__construct($filesystem);
        $this->adjustmentService = $adjustmentService;
    }

    public function table(): string
    {
        return 'inventory_adjustments';
    }

    public function modelName(): string
    {
        return InventoryAdjustment::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.inventory-adjustments';
    }

    public function routeParameter(): string
    {
        return 'inventory_adjustment';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::date('adjustment_date', 'تاریخ تعدیل')
                ->withDefaultValue(now())
                ->required(),

            Field::select('adjustment_type', 'نوع تعدیل')
                ->setOptions([
                    'physical_count' => 'شمارش فیزیکی',
                    'writedown' => 'کاهش ارزش',
                    'damage' => 'آسیب دیده',
                    'theft' => 'سرقت',
                    'obsolescence' => 'منسوخ شده',
                    'other' => 'سایر',
                ])
                ->withDefaultValue('physical_count')
                ->required(),

            Field::textarea('reason', 'دلیل', 3)
                ->required(),

            Field::textarea('notes', 'یادداشت', 2)
                ->optional(),
        ];
    }

    /**
     * مثل {@see ExpensesController::create()} — ابتدا قالب پکیج و پلاگین‌ها، سپس فرم پویا.
     */
    public function create(Request $request)
    {
        $this->configureInventoryAdjustmentFormView();

        return $this->generateForm();
    }

    /**
     * مثل {@see ExpensesController::edit()} — همان تنظیم نما قبل از generateForm.
     */
    public function edit(Request $request, int|string $id)
    {
        $this->configureInventoryAdjustmentFormView();

        return $this->generateForm($id);
    }

    /**
     * اگر جایی فقط setTplForm صدا زده شود (مثلاً مسیرهای جانبی هسته).
     */
    public function setTplForm(): void
    {
        $this->configureInventoryAdjustmentFormView();
    }

    protected function configureInventoryAdjustmentFormView(): void
    {
        $this->use_package_namespace = true;
        $this->tpl_form = 'inventory_adjustments.form';
        $this->view->usePackageNamespace('accounting');
        $this->view->setTpl('inventory_adjustments.form');

        if (AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI) {
            $this->view->withPlugins(['persian-datepicker']);
        }
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('adjustment_number')->withTitle('شماره')->searchable()->sortable()->width('150px'),
            Field::make('adjustment_date')->withTitle('تاریخ')->sortable()->width('120px'),
            Field::make('adjustment_type')->withTitle('نوع')->customMethod('renderType')->width('150px'),
            Field::number('total_adjustment_value')->withTitle('ارزش تعدیل')->sortable()->width('130px'),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus')->sortable()->width('120px'),
        ];
    }

    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date'],
            'adjustment_type' => ['required', 'in:physical_count,writedown,damage,theft,obsolescence,other'],
            'reason' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function approve(Request $request, $id)
    {
        try {
            $this->adjustmentService->approveAdjustment($id);
            return redirect()->back()->with('success', 'تعدیل تایید شد');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function post(Request $request, $id)
    {
        $request->validate([
            'inventory_account_id' => ['required', 'exists:accounts,id'],
            'adjustment_gain_account_id' => ['required', 'exists:accounts,id'],
            'adjustment_loss_account_id' => ['required', 'exists:accounts,id'],
            'writedown_account_id' => ['required', 'exists:accounts,id'],
        ]);

        try {
            $this->adjustmentService->postAdjustment($id, $request->only([
                'inventory_account_id',
                'adjustment_gain_account_id',
                'adjustment_loss_account_id',
                'writedown_account_id',
            ]));
            return redirect()->back()->with('success', 'تعدیل ثبت شد');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        try {
            $reversalAdjustment = $this->adjustmentService->reverseAdjustment($id, $request->reason);
            return redirect()->route('admin.accounting.inventory-adjustments.edit', $reversalAdjustment->id)
                ->with('success', 'تعدیل برگشتی ایجاد شد');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function renderStatus($row): string
    {
        $badges = [
            'draft' => '<span class="badge badge-warning">پیش‌نویس</span>',
            'approved' => '<span class="badge badge-info">تایید شده</span>',
            'posted' => '<span class="badge badge-success">ثبت شده</span>',
            'cancelled' => '<span class="badge badge-danger">لغو شده</span>',
        ];

        return $badges[$row->status] ?? $row->status;
    }

    public function renderType($row): string
    {
        $types = [
            'physical_count' => 'شمارش فیزیکی',
            'writedown' => 'کاهش ارزش',
            'damage' => 'آسیب دیده',
            'theft' => 'سرقت',
            'obsolescence' => 'منسوخ شده',
            'other' => 'سایر',
        ];

        return $types[$row->adjustment_type] ?? $row->adjustment_type;
    }
}
