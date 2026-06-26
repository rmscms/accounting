<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'report' }}</title>
    <style>body{font-family:sans-serif;padding:16px;} .note{color:#666;margin-bottom:12px;}</style>
</head>
<body>
    <p class="note">برای دریافت فایل PDF، سرویس SitePdfService در اپ باید در دسترس باشد. می‌توانید این صفحه را با Ctrl+P چاپ یا ذخیره به PDF کنید.</p>
    <h1 style="font-size:14pt;">{{ $data['title'] ?? '' }}</h1>
    @include('accounting::admin.reports.pdf.bank-statement-inner', ['data' => $data])
</body>
</html>
