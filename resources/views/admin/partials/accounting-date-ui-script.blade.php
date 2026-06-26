{{-- بارگذاری accounting-date-ui از route پکیج (هستهٔ RMS فقط در صورت وجود فایل در public، withJs را اضافه می‌کند) --}}
@once('rms_accounting_date_ui_script')
@push('scripts')
<script src="{{ route('admin.accounting.assets.accounting-date-ui') }}"></script>
@endpush
@endonce
