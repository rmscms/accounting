<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\Party;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Services\PartyService;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Data\Field;
use RMS\Core\Requests\Store;

class SuppliersController extends AccountingAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'suppliers';
    }

    public function modelName(): string
    {
        return Supplier::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.suppliers';
    }

    public function routeParameter(): string
    {
        return 'supplier';
    }

    /**
     * جستجوی Party برای پیوند (مشتری/طرف → تأمین‌کننده، صورتحساب یکپارچه از طریق party_id).
     */
    public function searchParties(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));
        $limit = max(5, min((int) $request->get('limit', 20), 50));

        $query = Party::query()->with(['customer', 'supplier']);

        if (Schema::hasColumn('parties', 'active')) {
            $query->where('parties.active', true);
        }

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('parties.name', 'like', '%'.$term.'%')
                    ->orWhere('parties.phone', 'like', '%'.$term.'%')
                    ->orWhere('parties.national_code', 'like', '%'.$term.'%');
            });
        }

        $parties = $query->orderBy('parties.name')->limit($limit)->get();

        $results = $parties->map(static function (Party $party) {
            $badges = [];
            $entityTypes = [];
            if ($party->relationLoaded('customer') && $party->customer) {
                $badges[] = trans('accounting::accounting.supplier.party_badge_customer');
                $entityTypes[] = 'customer';
            }
            if ($party->relationLoaded('supplier') && $party->supplier) {
                $badges[] = trans('accounting::accounting.supplier.party_badge_supplier');
                $entityTypes[] = 'supplier';
            }
            $badgeStr = $badges !== [] ? ' — '.implode('، ', $badges) : '';

            return [
                'id' => (string) $party->id,
                'customer_id' => $party->relationLoaded('customer') && $party->customer ? (string) $party->customer->id : '',
                'text' => (string) $party->name.$badgeStr,
                'name' => (string) $party->name,
                'entity_types' => $entityTypes,
                'entity_type_label' => implode('، ', $badges),
                'phone' => (string) ($party->phone ?? ''),
                'email' => (string) ($party->email ?? ''),
                'tax_number' => (string) ($party->tax_number ?? ''),
                'address' => (string) ($party->address ?? ''),
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }

    /**
     * جستجوی مشتری برای پیوند تأمین‌کننده به همان Party (در صورت نبود Party، هنگام ذخیره ساخته و به مشتری وصل می‌شود).
     */
    public function searchCustomersForSupplier(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));
        $limit = max(5, min((int) $request->get('limit', 20), 50));

        $query = Customer::query()->select(['id', 'name', 'phone', 'email', 'national_code', 'party_id']);

        if (Schema::hasColumn('customers', 'active')) {
            $query->where('customers.active', true);
        }

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('customers.name', 'like', '%'.$term.'%')
                    ->orWhere('customers.phone', 'like', '%'.$term.'%')
                    ->orWhere('customers.national_code', 'like', '%'.$term.'%')
                    ->orWhere('customers.email', 'like', '%'.$term.'%');
            });
        }

        $rows = $query->orderBy('customers.name')->limit($limit)->get();

        $results = $rows->map(static function (Customer $c) {
            $hint = trim(implode(' — ', array_filter([(string) $c->name, (string) ($c->phone ?? '')])));

            return [
                'id' => (string) $c->id,
                'text' => $hint !== '' ? $hint : ('#'.$c->id),
                'name' => (string) $c->name,
                'entity_type' => 'customer',
                'entity_type_label' => (string) trans('accounting::accounting.supplier.party_badge_customer'),
                'phone' => (string) ($c->phone ?? ''),
                'email' => (string) ($c->email ?? ''),
                'tax_number' => '',
                'address' => '',
                'party_id' => $c->party_id ? (string) $c->party_id : '',
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }

    /**
     * Join party for role column SQL (avoids N+1 on list).
     */
    public function query(Builder $sql): void
    {
        $sql->leftJoin('parties', 'parties.id', '=', 'a.party_id');
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'suppliers') {
            return;
        }

        $this->view->withJs('vendor/accounting/admin/js/suppliers-structured-party.js', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        if ($this->structuredAccountingFormSlug() !== 'suppliers') {
            return [];
        }

        $partySearchUrl = route('admin.accounting.suppliers.search-parties');
        $customerSearchUrl = route('admin.accounting.suppliers.search-customers');
        $defaultSupplierCode = null;
        $partySelectInitialText = null;
        $linkedCustomerPrefillId = null;
        $linkedCustomerSelectInitialText = null;
        $linkedCustomerPrefillValues = [];

        if (! $isEdit) {
            $defaultSupplierCode = app(PartyService::class)->suggestNextSupplierCode();
            $rawLinkedCustomer = $request->query('linked_customer_id');
            $linkedCustomerId = is_numeric($rawLinkedCustomer) ? (int) $rawLinkedCustomer : 0;
            if ($linkedCustomerId > 0) {
                $customer = Customer::query()->with('party')->find($linkedCustomerId);
                if ($customer) {
                    $linkedCustomerPrefillId = (string) $customer->id;
                    $linkedCustomerSelectInitialText = (string) $customer->name;
                    $linkedCustomerPrefillValues = [
                        'name' => (string) $customer->name,
                        'phone' => (string) ($customer->phone ?? ''),
                        'email' => (string) ($customer->email ?? ''),
                        // Supplier form uses tax_number; map from customer national_code.
                        'tax_number' => (string) ($customer->national_code ?? ($customer->party->national_code ?? '')),
                        'address' => (string) ($customer->party->address ?? ''),
                        'active' => 1,
                    ];
                }
            }
        } elseif ($model instanceof Supplier && $model->party_id) {
            $party = Party::query()->find((int) $model->party_id);
            if ($party) {
                $partySelectInitialText = (string) $party->name;
            }
        }

        return [
            'partySearchUrl' => $partySearchUrl,
            'customerSearchUrl' => $customerSearchUrl,
            'defaultSupplierCode' => $defaultSupplierCode,
            'partySelectInitialText' => $partySelectInitialText,
            'linkedCustomerPrefillId' => $linkedCustomerPrefillId,
            'linkedCustomerSelectInitialText' => $linkedCustomerSelectInitialText,
            'linkedCustomerPrefillValues' => $linkedCustomerPrefillValues,
        ];
    }

    public function getFieldsForm(): array
    {
        return [
            Field::number('linked_customer_id', trans('accounting::accounting.supplier.customer_link_label'))
                ->optional()
                ->withAttributes(['structured_widget' => 'ajax_customer_optional_select']),

            Field::number('party_id', trans('accounting::accounting.supplier.party_link_label'))
                ->optional()
                ->withAttributes(['structured_widget' => 'ajax_party_optional_select']),

            Field::string('name', trans('accounting::accounting.supplier.name'))->optional(),

            Field::string('code', trans('accounting::accounting.supplier.code'))->optional(),

            Field::string('tax_number', trans('accounting::accounting.supplier.tax_number'))->optional(),
            Field::string('phone', trans('accounting::accounting.supplier.phone'))->optional(),
            Field::string('email', trans('accounting::accounting.supplier.email'))->optional(),
            Field::textarea('address', trans('accounting::accounting.supplier.address'))->optional(),
            Field::boolean('active', trans('accounting::accounting.supplier.active'))->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('accounting::accounting.supplier.name'))->searchable()->sortable(),
            Field::make('code')->withTitle(trans('accounting::accounting.supplier.code'))->searchable()->sortable()->width('120px'),
            Field::make('phone')->withTitle(trans('accounting::accounting.supplier.phone'))->searchable()->width('150px'),
            Field::make('email')->withTitle(trans('accounting::accounting.supplier.email'))->searchable(),
            Field::boolean('active')->withTitle(trans('accounting::accounting.supplier.active'))->sortable()->width('100px'),
            Field::make(
                'party_has_customer',
                'CASE WHEN parties.id IS NOT NULL AND EXISTS (SELECT 1 FROM customers c2 WHERE c2.party_id = parties.id) THEN 1 ELSE 0 END',
                true
            )->withTitle('نقش')
                ->customMethod('renderPartyRoles')
                ->width('150px'),
        ];
    }

    /**
     * Badge for party role (supplier-only vs customer+supplier).
     */
    public function renderPartyRoles($row): string
    {
        $partyId = $row->party_id ?? null;
        if (! $partyId) {
            return '<span class="badge bg-secondary">تامین‌کننده</span>';
        }
        if ((int) ($row->party_has_customer ?? 0) === 1) {
            return '<span class="badge bg-primary">مشتری + تامین‌کننده</span>';
        }

        return '<span class="badge bg-secondary">تامین‌کننده</span>';
    }

    /**
     * Store supplier with party support
     */
    public function store(Store $request): RedirectResponse
    {
        $partyService = app(PartyService::class);

        $linkedRaw = $request->input('linked_customer_id');
        $linkedCustomerId = ($linkedRaw === '' || $linkedRaw === null) ? 0 : (int) $linkedRaw;
        if ($linkedCustomerId > 0) {
            $request->merge(['party_id' => null]);
        }

        $rawParty = $request->input('party_id');
        if ($rawParty === '' || $rawParty === null) {
            $request->merge(['party_id' => null]);
        }

        $rules = [
            'linked_customer_id' => 'nullable|integer|exists:customers,id',
            'party_id' => 'nullable|integer|exists:parties,id',
            'name' => ['required_without_all:party_id,linked_customer_id', 'nullable', 'string', 'max:255'],
            'code' => 'nullable|string|max:50|unique:suppliers,code',
            'tax_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'currency_code' => 'nullable|string|max:3',
            'payment_terms_days' => 'nullable|integer',
            'credit_limit' => 'nullable|numeric',
            'active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        $linkedCustomerId = (int) ($validated['linked_customer_id'] ?? 0);
        unset($validated['linked_customer_id']);

        $partyService->ensureSupplierCodeForStore($validated);

        $supplier = DB::transaction(function () use ($validated, $partyService, $linkedCustomerId) {
            if ($linkedCustomerId > 0) {
                $customer = Customer::query()->findOrFail($linkedCustomerId);
                $party = $partyService->ensurePartyForCustomer($customer);

                if (Supplier::query()->where('party_id', $party->id)->exists()) {
                    throw ValidationException::withMessages([
                        'linked_customer_id' => trans('accounting::accounting.supplier.linked_party_already_supplier'),
                    ]);
                }

                if (trim((string) ($validated['name'] ?? '')) === '') {
                    $validated['name'] = (string) $customer->name;
                }
                if (trim((string) ($validated['phone'] ?? '')) === '' && $customer->phone) {
                    $validated['phone'] = (string) $customer->phone;
                }
                if (trim((string) ($validated['email'] ?? '')) === '' && $customer->email) {
                    $validated['email'] = (string) $customer->email;
                }

                return $partyService->linkAsSupplier($party->id, $validated);
            }

            $partyId = isset($validated['party_id']) ? (int) $validated['party_id'] : 0;
            if ($partyId > 0) {
                $party = Party::findOrFail($partyId);
                if (trim((string) ($validated['name'] ?? '')) === '') {
                    $validated['name'] = (string) $party->name;
                }
                if (trim((string) ($validated['phone'] ?? '')) === '' && $party->phone) {
                    $validated['phone'] = (string) $party->phone;
                }
                if (trim((string) ($validated['email'] ?? '')) === '' && $party->email) {
                    $validated['email'] = (string) $party->email;
                }
                if (trim((string) ($validated['tax_number'] ?? '')) === '' && $party->tax_number) {
                    $validated['tax_number'] = (string) $party->tax_number;
                }
                if (trim((string) ($validated['address'] ?? '')) === '' && $party->address) {
                    $validated['address'] = (string) $party->address;
                }
                if (trim((string) ($validated['contact_person'] ?? '')) === '' && $party->contact_person) {
                    $validated['contact_person'] = (string) $party->contact_person;
                }

                return $partyService->linkAsSupplier($party->id, $validated);
            }

            $partyData = [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'type' => 'company',
            ];

            $party = $partyService->findOrCreateParty($partyData);

            return $partyService->linkAsSupplier($party->id, $validated);
        });

        if (trim((string) ($supplier->code ?? '')) === '') {
            $code = 'SUP-'.str_pad((string) $supplier->getKey(), 6, '0', STR_PAD_LEFT);
            if (Supplier::query()->where('code', $code)->where('id', '!=', $supplier->getKey())->exists()) {
                $code = $partyService->suggestNextSupplierCode();
            }
            $supplier->code = $code;
            $supplier->save();
        }

        $this->syncLinkedPartyFromSupplier($supplier);

        return redirect()->route($this->accountingNamedRoute('index'))
            ->with('success', trans('accounting::accounting.supplier.created'));
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof Supplier) {
            return;
        }

        $this->syncLinkedPartyFromSupplier($model->fresh());
    }

    protected function syncLinkedPartyFromSupplier(Supplier $supplier): void
    {
        if (! $supplier->party_id) {
            return;
        }

        $party = Party::query()->find((int) $supplier->party_id);
        if (! $party) {
            return;
        }

        $payload = [];
        $name = trim((string) ($supplier->name ?? ''));
        if ($name !== '') {
            $payload['name'] = $name;
        }

        foreach (['phone', 'email', 'tax_number', 'address', 'contact_person'] as $field) {
            $value = trim((string) ($supplier->{$field} ?? ''));
            if ($value !== '') {
                $payload[$field] = $value;
            }
        }

        if ($payload === []) {
            return;
        }

        $party->fill($payload);
        if ($party->isDirty()) {
            $party->saveQuietly();
        }
    }

    public function filters(): array
    {
        return [
            Field::select('active', trans('accounting::accounting.supplier.active'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    '1' => trans('accounting::accounting.common.active'),
                    '0' => trans('accounting::accounting.common.inactive'),
                ]),
        ];
    }
}
