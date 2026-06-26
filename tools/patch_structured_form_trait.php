<?php

$base = dirname(__DIR__) . '/src/Http/Controllers/Admin/';
$files = glob($base . '*Controller.php') ?: [];

$import = "use RMS\\Accounting\\Http\\Controllers\\Admin\\Concerns\\RendersAccountingStructuredResourceForm;\n";
$traitUse = "    use RendersAccountingStructuredResourceForm;\n\n";

$skipBasenames = [
    'BankTransfersController.php',
    'BadDebtController.php',
    'TaxRatesController.php',
    'InventoryAdjustmentsController.php',
    'ExpensesController.php',
    'BanksController.php',
    'ExpenseCategoriesController.php',
    'AccountsController.php',
];

foreach ($files as $path) {
    $f = basename($path);
    if (in_array($f, $skipBasenames, true)) {
        continue;
    }

    $c = file_get_contents($path);
    if (! str_contains($c, 'HasForm')) {
        continue;
    }
    if (str_contains($c, 'RendersAccountingStructuredResourceForm')) {
        continue;
    }
    if (preg_match('/class \w+ extends AccountingAdminController implements[\s\S]+?HasForm/', $c) !== 1) {
        continue;
    }

    $c2 = preg_replace('#(namespace RMS\\Accounting\\Http\\Controllers\\Admin;\R)#u', '$1' . $import, $c, 1);
    if ($c2 === null) {
        continue;
    }
    $c = $c2;

    $c2 = preg_replace(
        '#(class \w+ extends AccountingAdminController implements[\s\S]+?\{)\R#u',
        '$1' . "\n" . $traitUse,
        $c,
        1
    );
    if ($c2 === null || $c2 === $c) {
        fwrite(STDERR, "FAILED class brace $f\n");
        continue;
    }
    $c = $c2;

    file_put_contents($path, $c);
    echo "patched $f\n";
}
