<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص تطابق أرصدة الخزائن مع جدول transactions ===" . PHP_EOL;

$results = DB::select("
    SELECT
        cb.id,
        cb.user_id,
        cb.company_id,
        cb.name,
        cb.balance as stored_balance,
        COALESCE(SUM(
            CASE
                WHEN t.type IN ('deposit', 'transfer_in', 'reverse_withdraw', 'refund') THEN t.amount
                WHEN t.type IN ('withdraw', 'transfer_out', 'reverse_deposit', 'payment') THEN -t.amount
                ELSE 0
            END
        ), 0) as calculated_balance
    FROM cash_boxes cb
    LEFT JOIN transactions t ON t.cashbox_id = cb.id
    GROUP BY cb.id, cb.user_id, cb.company_id, cb.name, cb.balance
    HAVING ABS(cb.balance - calculated_balance) > 0.01
    LIMIT 20
");

echo 'عدد الخزائن مع رصيد غير متطابق: ' . count($results) . PHP_EOL;
if (count($results) > 0) {
    echo PHP_EOL;
    foreach ($results as $row) {
        $diff = $row->stored_balance - $row->calculated_balance;
        echo "خزنة #{$row->id} | الشركة:{$row->company_id} | المستخدم:{$row->user_id}" . PHP_EOL;
        echo "  الاسم: {$row->name}" . PHP_EOL;
        echo "  المخزون: {$row->stored_balance} | المحسوب: {$row->calculated_balance} | الفرق: {$diff}" . PHP_EOL;
    }
}

echo PHP_EOL . "=== توزيع الأدوار في company_user ===" . PHP_EOL;
$roles = DB::select("SELECT role, COUNT(*) as cnt FROM company_user GROUP BY role");
foreach ($roles as $r) {
    echo ($r->role ?? 'NULL') . ': ' . $r->cnt . PHP_EOL;
}

echo PHP_EOL . "=== خزائن بدون user_id ===" . PHP_EOL;
echo DB::table('cash_boxes')->whereNull('user_id')->count() . PHP_EOL;

echo PHP_EOL . "=== أنواع المعاملات الموجودة ===" . PHP_EOL;
$types = DB::select("SELECT type, COUNT(*) as cnt FROM transactions GROUP BY type ORDER BY cnt DESC");
foreach ($types as $t) {
    echo "{$t->type}: {$t->cnt}" . PHP_EOL;
}

echo PHP_EOL . "=== حجم جداول التقارير ===" . PHP_EOL;
echo "revenues: " . DB::table('revenues')->count() . PHP_EOL;
echo "profits: " . DB::table('profits')->count() . PHP_EOL;
echo "daily_sales_summary: " . DB::table('daily_sales_summary')->count() . PHP_EOL;
echo "monthly_sales_summary: " . DB::table('monthly_sales_summary')->count() . PHP_EOL;
echo "financial_ledger: " . DB::table('financial_ledger')->count() . PHP_EOL;

echo PHP_EOL . "=== فواتير حسب النوع ===" . PHP_EOL;
$invoiceTypes = DB::select("SELECT invoice_type_code, COUNT(*) as cnt FROM invoices GROUP BY invoice_type_code ORDER BY cnt DESC");
foreach ($invoiceTypes as $it) {
    echo ($it->invoice_type_code ?? 'NULL') . ': ' . $it->cnt . PHP_EOL;
}

echo PHP_EOL . "=== اختبار الـ user_company_cash (هل يُستخدم؟) ===" . PHP_EOL;
echo "user_company_cash rows: " . DB::table('user_company_cash')->count() . PHP_EOL;

echo PHP_EOL . "=== توزيع الخزائن حسب الشركة ===" . PHP_EOL;
$cbByCompany = DB::select("SELECT company_id, COUNT(*) as cnt FROM cash_boxes GROUP BY company_id ORDER BY cnt DESC LIMIT 5");
foreach ($cbByCompany as $c) {
    echo "شركة #{$c->company_id}: {$c->cnt} خزنة" . PHP_EOL;
}
