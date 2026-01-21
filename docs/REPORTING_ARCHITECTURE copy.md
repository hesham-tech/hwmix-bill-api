# معمارية التقارير والأداء - دليل شامل

> **الهدف:** بناء نظام تقارير احترافي بأعلى المعايير وأفضل الممارسات

---

## 📊 الوضع الحالي

### كيف تعمل التقارير حالياً؟

النظام يعتمد على **استعلامات فورية (Real-time Queries)** مباشرة من جداول قاعدة البيانات:

```php
// مثال: ProfitLossReportController.php
public function index(Request $request) {
    // استعلام مباشر لحساب الأرباح
    $revenue = Invoice::whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
        ->sum('net_amount');

    $costs = Invoice::whereHas('invoiceType', fn($q) => $q->where('code', 'purchase'))
        ->sum('net_amount');

    return ['profit' => $revenue - $costs];
}
```

### ✅ مميزات الطريقة الحالية:

- البيانات دائماً محدثة (100% دقيقة)
- بساطة في التطوير والصيانة
- لا حاجة لـ background jobs

### ❌ عيوب الطريقة الحالية:

- بطء مع كثرة البيانات (>100,000 فاتورة)
- حمل كبير على قاعدة البيانات
- احتمال timeout في التقارير الكبيرة

---

## 🏗️ معمارية التقارير الاحترافية (3 Levels)

### **Level 1: Real-time Queries** ⚡

```
📦 مناسب لـ: < 50,000 سجل
⚡ الأداء: سريع
🛠️ التعقيد: بسيط
✅ الوضع: الحالي
```

**متى نستخدمها؟**

- التقارير التفصيلية
- البيانات التي تتغير بسرعة
- الاستعلامات البسيطة

---

### **Level 2: Summary Tables** 🏆 (الموصى به)

#### تصميم الجداول الملخصة:

```sql
-- جدول ملخص المبيعات اليومية
CREATE TABLE daily_sales_summary (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    company_id BIGINT NOT NULL,

    -- الإيرادات
    total_revenue DECIMAL(15,2) DEFAULT 0,
    sales_count INT DEFAULT 0,

    -- التكاليف
    total_cogs DECIMAL(15,2) DEFAULT 0,      -- Cost of Goods Sold
    total_purchases DECIMAL(15,2) DEFAULT 0,

    -- الأرباح
    gross_profit DECIMAL(15,2) DEFAULT 0,    -- Revenue - COGS
    net_profit DECIMAL(15,2) DEFAULT 0,      -- Gross Profit - Expenses
    profit_margin DECIMAL(5,2) DEFAULT 0,

    -- إضافي
    customers_count INT DEFAULT 0,
    avg_order_value DECIMAL(15,2) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_date_company (date, company_id),
    INDEX idx_date (date),
    INDEX idx_company (company_id)
);

-- جدول ملخص شهري
CREATE TABLE monthly_sales_summary (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    year_month VARCHAR(7) NOT NULL, -- 'YYYY-MM'
    company_id BIGINT NOT NULL,

    total_revenue DECIMAL(15,2) DEFAULT 0,
    total_cogs DECIMAL(15,2) DEFAULT 0,
    net_profit DECIMAL(15,2) DEFAULT 0,
    profit_margin DECIMAL(5,2) DEFAULT 0,

    sales_count INT DEFAULT 0,
    customers_count INT DEFAULT 0,
    products_sold INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_month_company (year_month, company_id)
);
```

#### Laravel Migration:

```php
// database/migrations/YYYY_MM_DD_create_daily_sales_summary_table.php
public function up()
{
    Schema::create('daily_sales_summary', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->foreignId('company_id')->constrained()->onDelete('cascade');

        $table->decimal('total_revenue', 15, 2)->default(0);
        $table->decimal('total_cogs', 15, 2)->default(0);
        $table->decimal('gross_profit', 15, 2)->default(0);
        $table->decimal('net_profit', 15, 2)->default(0);
        $table->decimal('profit_margin', 5, 2)->default(0);

        $table->integer('sales_count')->default(0);
        $table->integer('customers_count')->default(0);

        $table->timestamps();

        $table->unique(['date', 'company_id'], 'unique_date_company');
        $table->index('date');
    });
}
```

#### Model:

```php
// app/Models/DailySalesSummary.php
class DailySalesSummary extends Model
{
    protected $fillable = [
        'date', 'company_id', 'total_revenue', 'total_cogs',
        'gross_profit', 'net_profit', 'profit_margin',
        'sales_count', 'customers_count'
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_cogs' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'profit_margin' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
```

#### تحديث البيانات - طريقة 1: Event-Driven

```php
// app/Observers/InvoiceObserver.php
class InvoiceObserver
{
    public function updated(Invoice $invoice)
    {
        // فقط عند تأكيد الفاتورة
        if ($invoice->wasChanged('status') && $invoice->status === 'confirmed') {
            dispatch(new UpdateDailySalesSummary($invoice));
        }
    }
}

// app/Jobs/UpdateDailySalesSummary.php
class UpdateDailySalesSummary implements ShouldQueue
{
    public function handle()
    {
        $date = $this->invoice->created_at->toDateString();
        $companyId = $this->invoice->company_id;

        // حساب البيانات من الفواتير المؤكدة
        $summary = Invoice::where('company_id', $companyId)
            ->whereDate('created_at', $date)
            ->whereIn('status', ['confirmed', 'paid'])
            ->selectRaw('
                SUM(CASE WHEN invoice_type_id = (SELECT id FROM invoice_types WHERE code = "sale") THEN net_amount ELSE 0 END) as revenue,
                COUNT(CASE WHEN invoice_type_id = (SELECT id FROM invoice_types WHERE code = "sale") THEN 1 END) as sales_count
            ')
            ->first();

        // حساب COGS
        $cogs = $this->calculateCOGS($date, $companyId);

        DailySalesSummary::updateOrCreate(
            ['date' => $date, 'company_id' => $companyId],
            [
                'total_revenue' => $summary->revenue ?? 0,
                'total_cogs' => $cogs,
                'gross_profit' => ($summary->revenue ?? 0) - $cogs,
                'sales_count' => $summary->sales_count ?? 0,
            ]
        );
    }

    private function calculateCOGS($date, $companyId)
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->join('invoice_types', 'invoices.invoice_type_id', '=', 'invoice_types.id')
            ->where('invoice_types.code', 'sale')
            ->where('invoices.company_id', $companyId)
            ->whereDate('invoices.created_at', $date)
            ->whereIn('invoices.status', ['confirmed', 'paid'])
            ->sum(DB::raw('invoice_items.quantity * products.purchase_price'));
    }
}
```

#### تحديث البيانات - طريقة 2: Scheduled Task

```php
// app/Console/Commands/GenerateDailySummaries.php
class GenerateDailySummaries extends Command
{
    protected $signature = 'reports:generate-daily-summaries {--date=}';

    public function handle()
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        Company::chunk(100, function ($companies) use ($date) {
            foreach ($companies as $company) {
                dispatch(new GenerateCompanyDailySummary($company->id, $date));
            }
        });
    }
}

// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // كل يوم الساعة 1 صباحاً
    $schedule->command('reports:generate-daily-summaries')
        ->dailyAt('01:00')
        ->onOneServer();
}
```

#### استخدام Summary Tables في التقارير:

```php
// app/Http/Controllers/Reports/ProfitLossReportController.php
public function index(Request $request)
{
    $from = $request->input('date_from', now()->startOfMonth()->toDateString());
    $to = $request->input('date_to', now()->toDateString());

    // استعلام بسيط وسريع جداً!
    $summary = DailySalesSummary::whereBetween('date', [$from, $to])
        ->whereCompanyIsCurrent()
        ->selectRaw('
            SUM(total_revenue) as total_revenue,
            SUM(total_cogs) as total_cogs,
            SUM(gross_profit) as gross_profit,
            SUM(net_profit) as net_profit
        ')
        ->first();

    return response()->json([
        'revenues' => ['total' => $summary->total_revenue],
        'costs' => ['total' => $summary->total_cogs],
        'result' => [
            'net_profit' => $summary->net_profit,
            'profit_margin' => $summary->total_revenue > 0
                ? ($summary->net_profit / $summary->total_revenue) * 100
                : 0
        ]
    ]);
}
```

---

### **Level 3: Data Warehouse + OLAP** 🌐

```
📦 مناسب لـ: ملايين السجلات
🏢 الاستخدام: أنظمة ضخمة (Amazon, SAP)
💰 التكلفة: عالية جداً
🛠️ الأدوات: Apache Druid, ClickHouse, BigQuery
```

**لا ننصح به حالياً** - مخصص للشركات الضخمة فقط.

---

## 🚀 تحسينات الأداء (Performance Optimization)

### 1. Database Indexing

```sql
-- لتسريع استعلامات التقارير
CREATE INDEX idx_invoices_composite
ON invoices(created_at, invoice_type_id, status, company_id);

CREATE INDEX idx_invoice_items_product
ON invoice_items(invoice_id, product_id);

CREATE INDEX idx_products_price
ON products(purchase_price, selling_price);

-- للبحث السريع
CREATE FULLTEXT INDEX idx_products_search
ON products(name, description);
```

### 2. Query Caching

```php
// config/cache.php - استخدام Redis
'default' => env('CACHE_DRIVER', 'redis'),

// في Controller
use Illuminate\Support\Facades\Cache;

public function monthlyReport($month)
{
    $cacheKey = "monthly_report_{$month}_" . auth()->user()->company_id;

    return Cache::remember($cacheKey, now()->addHours(6), function () use ($month) {
        return $this->generateMonthlyReport($month);
    });
}

// حذف الكاش عند التحديث
InvoiceObserver::updated() {
    Cache::forget('monthly_report_' . $invoice->created_at->format('Y-m'));
}
```

### 3. Query Optimization

```php
// ❌ سيء - N+1 Problem
$invoices = Invoice::all();
foreach ($invoices as $invoice) {
    echo $invoice->customer->name; // استعلام إضافي لكل فاتورة
}

// ✅ جيد - Eager Loading
$invoices = Invoice::with(['customer', 'items.product', 'invoiceType'])
    ->get();
```

### 4. Database Read Replicas

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST'),
    // ... master configuration
],
'mysql_read' => [
    'driver' => 'mysql',
    'host' => env('DB_READ_HOST'), // read replica
    // ...
],

// في Controller
DB::connection('mysql_read')
    ->table('invoices')
    ->get(); // للقراءة فقط
```

### 5. Queue System للتقارير الثقيلة

```php
// للتقارير التي تأخذ وقت طويل
dispatch(new GenerateAnnualReport($year, auth()->user()))
    ->onQueue('reports');

// app/Jobs/GenerateAnnualReport.php
class GenerateAnnualReport implements ShouldQueue
{
    public function handle()
    {
        $report = $this->generate();

        // إرسال للمستخدم عبر email أو notification
        $this->user->notify(new ReportReady($report));
    }
}
```

### 6. Pagination & Lazy Loading

```php
// ❌ سيء - تحميل كل البيانات
$invoices = Invoice::all(); // Out of Memory!

// ✅ جيد - Cursor للبيانات الكبيرة
Invoice::where('company_id', $companyId)
    ->cursor() // Generator - لا يحمل كل البيانات
    ->each(function ($invoice) {
        // معالجة واحد تلو الآخر
    });

// ✅ جيد - Chunking
Invoice::where('company_id', $companyId)
    ->chunk(1000, function ($invoices) {
        foreach ($invoices as $invoice) {
            // معالجة 1000 فاتورة في المرة
        }
    });
```

---

## 🔍 Monitoring & Debugging

### 1. Laravel Telescope

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

```php
// config/telescope.php
'watchers' => [
    Watchers\QueryWatcher::class => [
        'enabled' => true,
        'slow' => 100, // تنبيه للاستعلامات أبطأ من 100ms
    ],
],
```

### 2. Slow Query Logging

```sql
-- في MySQL
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- ثانية واحدة
```

### 3. Application Monitoring

```php
// استخدام New Relic أو Sentry
// composer require sentry/sentry-laravel

Log::info('Report Generated', [
    'type' => 'profit_loss',
    'duration' => $duration,
    'records' => $count
]);
```

---

## 📋 خطة التنفيذ الموصى بها

### **المرحلة 1: الإصلاحات الفورية** (أسبوع واحد)

- [x] إصلاح حساب COGS في تقرير الأرباح
- [ ] إضافة Database Indexes الأساسية
- [ ] تفعيل Query Caching للتقارير المتكررة
- [ ] إصلاح N+1 queries

```bash
# تشغيل
php artisan optimize
php artisan config:cache
php artisan route:cache
```

### **المرحلة 2: Summary Tables** (2-3 أسابيع)

- [ ] إنشاء جداول `daily_sales_summary`
- [ ] إنشاء جداول `monthly_sales_summary`
- [ ] تطوير Jobs لتحديث البيانات
- [ ] إعداد Laravel Scheduler
- [ ] تحديث Controllers للاستعلام من Summary Tables

### **المرحلة 3: Monitoring & Testing** (أسبوع واحد)

- [ ] تثبيت Laravel Telescope
- [ ] كتابة Feature Tests للتقارير
- [ ] إعداد Performance Benchmarks
- [ ] مراجعة وتحسين Slow Queries

### **المرحلة 4: المزايا الإضافية** (شهر واحد)

- [ ] Queue System للتقارير الثقيلة
- [ ] Report Scheduling (إرسال تلقائي)
- [ ] Dashboard للـ Real-time Metrics
- [ ] Data Export في الخلفية

---

## 🎯 Best Practices - أفضل الممارسات

### 1. استخدم Service Classes

```php
// app/Services/Reports/ProfitReportService.php
class ProfitReportService
{
    public function generate(array $filters): array
    {
        // Business logic هنا وليس في Controller
        return [
            'revenues' => $this->calculateRevenues($filters),
            'costs' => $this->calculateCosts($filters),
            'profit' => $this->calculateProfit($filters),
        ];
    }
}

// في Controller
public function index(Request $request)
{
    $report = app(ProfitReportService::class)->generate($request->all());
    return response()->json($report);
}
```

### 2. Repository Pattern للاستعلامات المعقدة

```php
// app/Repositories/InvoiceRepository.php
class InvoiceRepository
{
    public function getSalesByDateRange($from, $to, $companyId = null)
    {
        return Invoice::whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->whereBetween('created_at', [$from, $to])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->get();
    }
}
```

### 3. Data Transfer Objects (DTOs)

```php
// app/DTOs/ProfitReportDTO.php
class ProfitReportDTO
{
    public function __construct(
        public readonly float $totalRevenue,
        public readonly float $totalCosts,
        public readonly float $netProfit,
        public readonly float $profitMargin,
    ) {}

    public function toArray(): array
    {
        return [
            'total_revenue' => $this->totalRevenue,
            'total_costs' => $this->totalCosts,
            'net_profit' => $this->netProfit,
            'profit_margin' => $this->profitMargin,
        ];
    }
}
```

### 4. Feature Tests

```php
// tests/Feature/Reports/ProfitReportTest.php
test('profit report calculates COGS correctly', function () {
    $product = Product::factory()->create(['purchase_price' => 400]);

    $invoice = Invoice::factory()->create(['net_amount' => 600]);
    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 600,
    ]);

    $response = $this->get('/api/reports/profit-loss');

    expect($response->json('result.net_profit'))->toBe(200.0);
});
```

---

## 📚 مراجع إضافية

- [Laravel Performance Best Practices](https://laravel.com/docs/optimization)
- [Database Indexing Guide](https://use-the-index-luke.com/)
- [Query Optimization](https://www.percona.com/blog/)
- [Laravel Telescope Documentation](https://laravel.com/docs/telescope)

---

## 💡 نصائح ختامية

1. **ابدأ بسيط** - لا تعقد الأمور من البداية
2. **قس الأداء** - استخدم Telescope لمعرفة أين المشكلة
3. **اختبر دائماً** - Feature Tests تمنع الأخطاء
4. **وثق كل شيء** - Documentation = صيانة أسهل
5. **راقب باستمرار** - Monitoring يكشف المشاكل مبكراً

---

**تاريخ الإنشاء:** 2026-01-20  
**آخر تحديث:** 2026-01-20  
**الإصدار:** 1.0
