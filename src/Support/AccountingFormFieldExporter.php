<?php

namespace RMS\Accounting\Support;

use RMS\Core\Data\Field;

/**
 * تبدیل Fieldهای getFieldsForm() به آرایهٔ ساده برای رندر Blade ساختاریافته.
 */
final class AccountingFormFieldExporter
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function toViewRows(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                continue;
            }
            $out[] = self::exportOne($field);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportOne(Field $field): array
    {
        $key = $field->key;
        $label = (string) ($field->title ?? $key);
        $required = (bool) $field->required;
        $rows = isset($field->attributes['rows']) ? (int) $field->attributes['rows'] : 3;

        $widget = 'text';
        $extra = [];

        switch ($field->type) {
            case Field::TEXTAREA:
                $widget = 'textarea';
                $extra['rows'] = max(2, $rows);
                break;
            case Field::DATE:
            case Field::DATE_TIME:
                $widget = 'date';
                break;
            case Field::BOOL:
                $widget = 'boolean';
                break;
            case Field::HIDDEN:
                $widget = 'hidden';
                if ($field->default_value !== null) {
                    $extra['default_value'] = $field->default_value;
                }
                break;
            case Field::SELECT:
                $widget = 'select';
                $extra['options'] = self::selectOptions($field);
                $extra['use_enhanced'] = count($extra['options']) > 12;
                if ($field->default_value !== null) {
                    $extra['default_value'] = $field->default_value;
                }
                break;
            case Field::PRICE:
                $widget = 'amount';
                break;
            case Field::NUMBER:
                $sw = (string) ($field->attributes['structured_widget'] ?? '');
                if ($sw === 'ajax_supplier_select') {
                    $widget = 'ajax_supplier_select';
                    break;
                }
                if ($sw === 'ajax_supplier_invoice_select') {
                    $widget = 'ajax_supplier_invoice_select';
                    $extra['depends_on_field'] = (string) ($field->attributes['depends_on_field'] ?? 'supplier_id');
                    $extra['supplier_invoice_search_url'] = (string) ($field->attributes['supplier_invoice_search_url'] ?? '');

                    break;
                }
                if ($sw === 'ajax_party_optional_select') {
                    $widget = 'ajax_party_optional_select';
                    break;
                }
                if ($sw === 'ajax_customer_optional_select') {
                    $widget = 'ajax_customer_optional_select';
                    break;
                }
                if ($sw === 'ajax_customer_select') {
                    $widget = 'ajax_customer_select';
                    break;
                }
                if ($sw === 'customer_payment_customer_picker') {
                    $widget = 'customer_payment_customer_picker';
                    break;
                }
                if ($sw === 'payment_destination_picker') {
                    $widget = 'payment_destination_picker';
                    $extra['payment_destination_context'] = (string) ($field->attributes['payment_destination_context'] ?? 'supplier_payment');
                    $extra['payment_destination_catalog_url'] = (string) ($field->attributes['payment_destination_catalog_url'] ?? '');
                    $extra['pdp_name_prefix'] = (string) ($field->attributes['pdp_name_prefix'] ?? '');
                    $extra['wrap_settlement_destination'] = (bool) ($field->attributes['wrap_settlement_destination'] ?? false);
                    break;
                }
                if ($sw === 'supplier_payment_purchase_order') {
                    $widget = 'supplier_payment_purchase_order';
                    break;
                }
                $widget = self::inferNumberWidget($key);
                if ($widget === 'amount') {
                    $extra['decimals'] = null;
                }
                break;
            default:
                $widget = 'text';
                break;
        }

        if ($field->default_value !== null && ! array_key_exists('default_value', $extra)) {
            $extra['default_value'] = $field->default_value;
        }

        return array_merge([
            'key' => $key,
            'label' => $label,
            'required' => $required,
            'widget' => $widget,
            'rtl' => (bool) $field->rtl,
        ], $extra);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function selectOptions(Field $field): array
    {
        if ($field->select_data === null) {
            return [];
        }
        $idKey = $field->select_id ?? 'id';
        $nameKey = $field->select_title ?? 'name';
        $opts = [];
        foreach ($field->select_data as $row) {
            if (is_array($row)) {
                $v = (string) ($row[$idKey] ?? '');
                $l = (string) ($row[$nameKey] ?? $v);
            } else {
                $v = (string) data_get($row, $idKey, '');
                $l = (string) data_get($row, $nameKey, $v);
            }
            $opts[] = ['value' => $v, 'label' => $l];
        }

        return $opts;
    }

    private static function inferNumberWidget(string $key): string
    {
        $k = strtolower($key);
        foreach (['amount', 'price', 'rate', 'fee', 'limit', 'total', 'debit', 'credit', 'balance', 'qty', 'quantity'] as $hint) {
            if (str_contains($k, $hint)) {
                return 'amount';
            }
        }

        return 'integer';
    }
}
