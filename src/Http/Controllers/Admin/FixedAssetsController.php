<?php

namespace RMS\Accounting\Http\Controllers\Admin;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\FixedAsset;
use RMS\Accounting\Models\FixedAssetCategory;
use RMS\Accounting\Services\FixedAssetService;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

/**
 * مدیریت دارایی‌های ثابت
 */
class FixedAssetsController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected FixedAssetService $fixedAssetService;

    public function __construct(Filesystem $filesystem, FixedAssetService $fixedAssetService)
    {
        parent::__construct($filesystem);
        $this->fixedAssetService = $fixedAssetService;
    }

    public function table(): string
    {
        return 'fixed_assets';
    }

    public function modelName(): string
    {
        return FixedAsset::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.fixed-assets';
    }

    public function routeParameter(): string
    {
        return 'fixed_asset';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('asset_code', trans('accounting::accounting.fixed_asset.code'))
                ->setPlaceholder((string) trans('accounting::accounting.fixed_asset.code_auto_placeholder'))
                ->withDefaultValue(FixedAsset::generateAssetCode())
                ->optional(),

            Field::string('name', trans('accounting::accounting.fixed_asset.name'))
                ->required(),

            Field::select('category_id', trans('accounting::accounting.fixed_asset.category'))
                ->setOptions($this->getCategoryOptions())
                ->advanced()
                ->required(),

            Field::date('purchase_date', trans('accounting::accounting.fixed_asset.purchase_date'))
                ->withDefaultValue(now()->toDateString())
                ->required(),

            Field::number('purchase_price', trans('accounting::accounting.fixed_asset.purchase_price'))
                ->required(),

            Field::select('payment_account_id', trans('accounting::accounting.fixed_asset.payment_account_id'))
                ->setOptions($this->getBankPaymentAccountOptions())
                ->advanced()
                ->optional(),

            Field::number('useful_life_years', trans('accounting::accounting.fixed_asset.useful_life_years'))
                ->withDefaultValue(5)
                ->required(),

            Field::select('depreciation_method', trans('accounting::accounting.fixed_asset.depreciation_method'))
                ->setOptions([
                    'straight_line' => trans('accounting::accounting.fixed_asset.methods.straight_line'),
                    'declining_balance' => trans('accounting::accounting.fixed_asset.methods.declining_balance'),
                    'units_of_production' => trans('accounting::accounting.fixed_asset.methods.units_of_production'),
                ])
                ->withDefaultValue('straight_line')
                ->required(),

            Field::number('salvage_value', trans('accounting::accounting.fixed_asset.salvage_value'))
                ->withDefaultValue(0)
                ->optional(),

            Field::string('location', trans('accounting::accounting.fixed_asset.location'))
                ->optional(),

            Field::string('serial_number', trans('accounting::accounting.fixed_asset.serial_number'))
                ->optional(),

            Field::textarea('description', trans('accounting::accounting.common.description'), 3)
                ->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('asset_code')->withTitle(trans('accounting::accounting.fixed_asset.code'))->searchable()->sortable()->width('120px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fixed_asset.name'))->searchable()->sortable(),
            Field::make('category.name')->withTitle(trans('accounting::accounting.fixed_asset.category'))->width('150px'),
            Field::number('purchase_price')->withTitle(trans('accounting::accounting.fixed_asset.purchase_price'))->sortable()->width('120px'),
            Field::number('book_value')->withTitle(trans('accounting::accounting.fixed_asset.book_value'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.common.status'))->customMethod('renderStatus')->width('120px'),
        ];
    }

    public function rules(): array
    {
        $id = request()->route('fixed_asset');

        return [
            'asset_code' => ['nullable', 'string', 'max:50', 'unique:fixed_assets,asset_code,' . $id],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:fixed_asset_categories,id'],
            'purchase_date' => ['required', 'date'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'useful_life_years' => ['required', 'integer', 'min:1'],
            'depreciation_method' => ['required', 'in:straight_line,declining_balance,units_of_production'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * تولید برنامه استهلاک
     */
    public function generateSchedule(Request $request, $id)
    {
        try {
            $this->fixedAssetService->generateDepreciationSchedule($id);
            return redirect()->back()->with('success', trans('accounting::accounting.fixed_asset.messages.schedule_generated'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * ثبت استهلاک
     */
    public function recordDepreciation(Request $request, $id)
    {
        $request->validate([
            'period_date' => ['required', 'date'],
        ]);

        try {
            $this->fixedAssetService->recordDepreciation($id, $request->period_date);
            return redirect()->back()->with('success', trans('accounting::accounting.fixed_asset.messages.depreciation_recorded'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * فروش/خروج دارایی
     */
    public function dispose(Request $request, $id)
    {
        $request->validate([
            'disposal_date' => ['required', 'date'],
            'disposal_value' => ['required', 'numeric', 'min:0'],
            'cash_account_id' => ['required', 'exists:accounts,id'],
            'gain_account_id' => ['required', 'exists:accounts,id'],
            'loss_account_id' => ['required', 'exists:accounts,id'],
        ]);

        try {
            $this->fixedAssetService->disposeAsset($id, $request->all());
            return redirect()->route('admin.accounting.fixed-assets.index')
                ->with('success', trans('accounting::accounting.fixed_asset.messages.disposed'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function renderStatus($row): string
    {
        $badges = [
            'active' => '<span class="badge bg-success">'.e(trans('accounting::accounting.fixed_asset.statuses.active')).'</span>',
            'disposed' => '<span class="badge bg-secondary">'.e(trans('accounting::accounting.fixed_asset.statuses.disposed')).'</span>',
            'fully_depreciated' => '<span class="badge bg-warning text-dark">'.e(trans('accounting::accounting.fixed_asset.statuses.fully_depreciated')).'</span>',
        ];

        return $badges[$row->status] ?? e((string) $row->status);
    }

    public function beforeAdd(Request &$request): void
    {
        $assetCode = trim((string) $request->input('asset_code', ''));
        $purchaseDate = trim((string) $request->input('purchase_date', ''));

        $request->merge([
            'asset_code' => $assetCode !== '' ? $assetCode : FixedAsset::generateAssetCode(),
            'purchase_date' => $purchaseDate !== '' ? $purchaseDate : now()->toDateString(),
            'book_value' => (float) $request->input('purchase_price', 0),
            'status' => (string) ($request->input('status') ?: 'active'),
        ]);

        // فقط برای UX فرم است و در جدول fixed_assets ذخیره نمی‌شود.
        $request->request->remove('payment_account_id');
    }

    public function beforeUpdate(Request &$request, string|int $id): void
    {
        $request->request->remove('payment_account_id');
    }

    protected function getCategoryOptions(): array
    {
        return FixedAssetCategory::where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getBankPaymentAccountOptions(): array
    {
        $options = ['' => (string) trans('accounting::accounting.fixed_asset.payment_account_placeholder')];

        $banks = Bank::query()
            ->where('active', true)
            ->whereNotNull('account_id')
            ->orderBy('name')
            ->get(['id', 'name', 'account_id']);

        foreach ($banks as $bank) {
            $accountId = (int) ($bank->account_id ?? 0);
            if ($accountId <= 0) {
                continue;
            }
            $options[(string) $accountId] = (string) $bank->name . ' (#' . $bank->id . ')';
        }

        return $options;
    }
}
