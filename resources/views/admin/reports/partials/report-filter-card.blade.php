{{-- فیلتر تاریخ و چاپ (الگوی card toolbar مشابه Limitless) --}}
<div class="card mb-3">
    <div class="card-body">
        <form id="accounting-reports-filter-form" method="GET" class="row g-3 align-items-end">
            @if(!isset($data['error']))
            <x-accounting::date-range-filter
                :from-value="$reportFromVal"
                :to-value="$reportToVal"
                from-col-class="col-md-3"
                to-col-class="col-md-3"
            />

            @if(!empty($data['vat_quick_periods']) && is_array($data['vat_quick_periods']))
            <div class="col-md-3">
                <label class="form-label">{{ trans('accounting::accounting.reports.vat.quick_periods_label') }}</label>
                <select name="vat_quick_period" class="form-select js-vat-quick-period-select">
                    <option value="">{{ trans('accounting::accounting.reports.vat.quick_periods_placeholder') }}</option>
                    @foreach($data['vat_quick_periods'] as $period)
                    <option value="{{ ($period['from'] ?? '').'|'.($period['to'] ?? '') }}">
                        {{ $period['label'] ?? (($period['from'] ?? '').' تا '.($period['to'] ?? '')) }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(isset($data['filter_customer']))
            <div class="col-md-3">
                <label class="form-label">مشتری</label>
                <select name="customer_id" class="form-select">
                    <option value="">همه</option>
                    @foreach($data['customers'] ?? [] as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(isset($data['filter_supplier']))
            <div class="col-md-3">
                <label class="form-label">تامین‌کننده</label>
                <select name="supplier_id" class="form-select">
                    <option value="">همه</option>
                    @foreach($data['suppliers'] ?? [] as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(isset($data['filter_employee']))
            <div class="col-md-3">
                <label class="form-label">کارمند</label>
                <select name="employee_id" class="form-select">
                    <option value="">همه</option>
                    @foreach($data['employees'] ?? [] as $employee)
                    <option value="{{ $employee->id }}" @selected((int) request('employee_id') === (int) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(isset($data['filter_status_options']) && is_array($data['filter_status_options']))
            <div class="col-md-3">
                <label class="form-label">وضعیت</label>
                <select name="status" class="form-select">
                    <option value="">همه</option>
                    @foreach($data['filter_status_options'] as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}" @selected((string) request('status') === (string) $statusValue)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(!empty($data['filter_account_code']))
            <div class="col-md-3">
                <label class="form-label">کد حساب / تفصیلی</label>
                <input type="text"
                       name="account_code"
                       class="form-control"
                       value="{{ request('account_code', $data['selected_account_code'] ?? '') }}"
                       placeholder="مثال: 1201-001">
                <div class="form-text">برای جست‌وجوی مانده مشتری با کد تفصیلی وارد کنید.</div>
            </div>
            @endif

            @if(request('flat'))
            <input type="hidden" name="flat" value="1" />
            @endif
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="ph-funnel me-1"></i> اعمال فیلتر
                </button>
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="ph-printer me-1"></i> چاپ
                </button>
            </div>
            @endif
        </form>
    </div>
</div>
