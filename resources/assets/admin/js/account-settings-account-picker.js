(function () {
    'use strict';

    function buildFieldConfig() {
        return {
            accounts_receivable_account_code: { types: ['asset'], valueKey: 'code' },
            accounts_payable_account_code: { types: ['liability'], valueKey: 'code' },
            inventory_account_code: { types: ['asset'], valueKey: 'code' },
            cheques_receivable_clearing_account_code: { types: ['asset'], valueKey: 'code' },
            cheques_payable_clearing_account_code: { types: ['liability'], valueKey: 'code' },
            treasury_bank_parent_account_code: { types: ['asset'], valueKey: 'code' },
            treasury_cashbox_parent_account_code: { types: ['asset'], valueKey: 'code' },
            fx_difference_account_code: { types: ['expense', 'income', 'revenue'], valueKey: 'code' },
            fx_gain_account_code: { types: ['income', 'revenue'], valueKey: 'code' },
            fx_loss_account_code: { types: ['expense'], valueKey: 'code' },
            wages_payable_account_code: { types: ['liability'], valueKey: 'code' },
            social_insurance_payable_account_code: { types: ['liability'], valueKey: 'code' },
            employee_insurance_payable_account_code: { types: ['liability'], valueKey: 'code' },
            employer_insurance_payable_account_code: { types: ['liability'], valueKey: 'code' },
            payroll_tax_payable_account_code: { types: ['liability'], valueKey: 'code' },
            other_payroll_deductions_payable_account_code: { types: ['liability'], valueKey: 'code' },
            payroll_seniority_reserve_account_code: { types: ['liability'], valueKey: 'code' },
            employer_social_insurance_account_code: { types: ['expense'], valueKey: 'code' },
            payroll_seniority_account_code: { types: ['expense'], valueKey: 'code' },
            employee_loans_receivable_account_code: { types: ['asset'], valueKey: 'code' },
            employee_loan_interest_income_account_code: { types: ['income', 'revenue'], valueKey: 'code' },
            equity_capital_account_code: { types: ['equity'], valueKey: 'code' },
            retained_earnings_account_code: { types: ['equity'], valueKey: 'code' },
            income_summary_account_code: { types: ['equity'], valueKey: 'code' },
            shareholder_drawings_account_code: { types: ['equity'], valueKey: 'code' },
            bank_interest_income_account_code: { types: ['income', 'revenue'], valueKey: 'code' },
            bank_charges_account_code: { types: ['expense'], valueKey: 'code' },
            vat_account_payable_id: { types: ['liability'], valueKey: 'id' },
            vat_account_receivable_id: { types: ['asset'], valueKey: 'id' },
            income_tax_expense_account_id: { types: ['expense'], valueKey: 'id' },
            income_tax_payable_account_id: { types: ['liability'], valueKey: 'id' }
        };
    }

    function initAccountSettingsPickers() {
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }

        var root = document.querySelector('[data-account-search-url]');
        var globalSearchUrl = root ? String(root.getAttribute('data-account-search-url') || '').trim() : '';
        if (globalSearchUrl === '') {
            return;
        }

        var $ = window.jQuery;
        var config = buildFieldConfig();

        Object.keys(config).forEach(function (fieldName) {
            var $el = $('select[name="' + fieldName + '"]');
            if (!$el.length) {
                return;
            }

            var fieldConfig = config[fieldName] || {};
            var types = Array.isArray(fieldConfig.types) ? fieldConfig.types : [];
            var valueKey = fieldConfig.valueKey === 'id' ? 'id' : 'code';
            var searchUrl = String($el.data('search-url') || globalSearchUrl).trim();
            if (searchUrl === '') {
                return;
            }

            if ($el.data('select2')) {
                $el.select2('destroy');
            }

            var placeholder = String($el.data('placeholder') || '').trim();
            if (placeholder === '') {
                var firstOption = $el.find('option').first();
                placeholder = firstOption.length ? String(firstOption.text() || '').trim() : '';
            }
            if (placeholder === '') {
                placeholder = '—';
            }

            $el.select2({
                width: '100%',
                dir: 'rtl',
                allowClear: true,
                minimumInputLength: 2,
                placeholder: placeholder,
                ajax: {
                    url: searchUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: (params.term != null ? params.term : '') || '',
                            types: types.join(','),
                            value_key: valueKey
                        };
                    },
                    processResults: function (data) {
                        return { results: data && Array.isArray(data.results) ? data.results : [] };
                    },
                    cache: true
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initAccountSettingsPickers);
})();
