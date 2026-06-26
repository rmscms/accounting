<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'warehouse-receipt' }}</title>
    <style>
        body { font-family: sans-serif; padding: 16px; font-size: 10pt; }
        .note { color: #666; margin-bottom: 12px; }
        h1 { font-size: 14pt; margin-bottom: 4px; }
        .meta { font-size: 9pt; color: #444; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th { background: #f0f0f0; }
        .num { text-align: left; direction: ltr; }
        .sign { margin-top: 36px; font-size: 9pt; }
    </style>
</head>
<body>
    <p class="note">{{ trans('accounting::accounting.purchase_order.warehouse_receipt_pdf_fallback_note') }}</p>
    @include('accounting::admin.purchase_orders.pdf._warehouse_receipt_body', ['order' => $order])
</body>
</html>
