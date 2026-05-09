# 📐 معايير وقواعد التطوير الإلزامية — HWNix Bill ERP

> هذه القواعد **إلزامية بالكامل** وليست اقتراحات.
> أي كود يخالفها = Technical Debt يجب مراجعته فوراً.

---

## 🧭 الفلسفة الأساسية

نحن لا نبني "Laravel CRUD System".
نبني **ERP Platform + Financial System + Multi-Tenant SaaS**.

لذلك: الجودة، الأمان، قابلية التوسع، والاستقرار **ليست أمور ثانوية — بل هي المشروع نفسه**.

---

## 📌 القاعدة الذهبية

> **"اسأل دائماً: هل هذا التصميم سيظل صحيحاً بعد سنتين وعشرة عملاء؟"**

إذا كانت الإجابة لا → أعد التصميم.

---

## 1️⃣ قواعد عامة أساسية

### ✅ Zero Breaking Changes
- لا تكسر API موجود
- لا تغيّر سلوك دالة يعتمد عليها النظام
- لا تحذف Column مستخدم بدون Migration plan
- عند التغيير الكبير → Versioning أو Adapter Pattern

### ✅ كل Feature مكتملة أو لا تُضاف
كل ميزة **يجب** أن تحتوي على:
```
✅ Migration/Database
✅ API Endpoints
✅ Permissions
✅ Validation (Form Request)
✅ Events
✅ Tests
✅ Documentation
```
**ممنوع**: إنشاء جدول الآن واكتمال الكود "لاحقاً".

### ✅ Feature Flags للميزات الجديدة
أي ميزة جديدة حساسة → تُفعَّل عبر flag أولاً.

### ✅ Modular Thinking دائماً
```
✅ Modules/Invoices/
✅ Modules/Accounting/
✅ Modules/Inventory/

❌ Controllers عشوائية
❌ Helpers ضخمة غير منظمة
❌ Functions متناثرة
```

### ✅ إلزامية Code Review
لا يدخل كود حرج (مالي، صلاحيات، DB) إلى `main` بدون مراجعة.

---

## 2️⃣ قواعد Architecture

### ✅ Controllers = Thin Layer فقط
```php
// ✅ صح
public function store(CreateInvoiceRequest $request)
{
    $dto = CreateInvoiceDTO::fromRequest($request);
    $invoice = $this->invoiceService->create($dto);
    return api_success(InvoiceResource::make($invoice));
}

// ❌ غلط — Business Logic داخل Controller
public function store(Request $request)
{
    $total = 0;
    foreach ($request->items as $item) { ... } // منطق عمل هنا!
    Invoice::create([...]);
}
```

**Controller وظيفته فقط:**
1. استقبال الطلب
2. التحقق الأولي (via Form Request)
3. استدعاء Service/Action
4. إعادة Response موحد

### ✅ Service / Action Layer إلزامي للمنطق الحقيقي
```
app/Services/InvoiceService.php      → يتعامل مع Workflow كامل
app/Actions/CreateInvoiceAction.php  → عملية واحدة محددة
```

### ✅ DTOs لنقل البيانات بين الطبقات
```php
// ✅ صح
class CreateInvoiceDTO {
    public function __construct(
        public readonly int $customerId,
        public readonly float $total,
        public readonly string $currency,
    ) {}
    
    public static function fromRequest(Request $r): self { ... }
}

// ❌ غلط
$this->invoiceService->create($request->all()); // Array عشوائي
```

### ✅ Repository Pattern للأجزاء الحرجة فقط
مطلوب في: **Transactions, Invoices, Accounting**
غير مطلوب في كل مكان (Over-engineering).

### ✅ Single Responsibility Principle
كل Class/Function لها **وظيفة واحدة** فقط.
إذا احتجت "و" في الوصف → قسّمها.

### ✅ Dependency Injection دائماً
```php
// ✅ صح
class InvoiceController {
    public function __construct(private InvoiceService $service) {}
}

// ❌ غلط
class InvoiceController {
    public function store() {
        $service = new InvoiceService(); // Hardcoded dependency
    }
}
```

### ✅ لا تعديل مباشر على Core
عند الحاجة لتغيير سلوك موجود:
```
✅ Extend → Override
✅ Decorator Pattern
✅ Adapter Pattern
❌ تعديل مباشر في ملفات Core أو Vendor
```

---

## 3️⃣ قواعد قاعدة البيانات (إلزامية)

### ✅ كل جدول تابع لشركة MUST يحتوي على:
```sql
company_id  BIGINT UNSIGNED NOT NULL  -- Multi-tenancy
created_by  BIGINT UNSIGNED NULL      -- Audit Trail
```
**استثناء**: Pivot Tables، Global Tables، Lookup Tables.

### ✅ كل جدول MUST يحتوي على:
```sql
created_at  TIMESTAMP
updated_at  TIMESTAMP
-- وعند الحاجة:
deleted_at  TIMESTAMP  -- Soft Delete
```

### ✅ العمليات المالية = DB Transaction إلزامي
```php
// ✅ صح
DB::transaction(function () {
    $this->debitCashBox($amount);
    $this->createTransactionLog($amount);
    $this->notifyUser();
});

// ❌ غلط — عمليتان بدون transaction
$cashBox->balance -= $amount;
$cashBox->save();
Transaction::create([...]); // لو فشلت → رصيد خُصم بدون سجل!
```

### ✅ الماليات = decimal(18,2) فقط
```sql
-- ✅ صح
amount DECIMAL(18,2) NOT NULL

-- ❌ غلط
amount FLOAT  -- دقة غير مضمونة في الحسابات
amount DOUBLE
```

### ✅ Foreign Keys إلزامية
```sql
FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
```

### ✅ Indexes إلزامية على:
```sql
INDEX(company_id)
INDEX(created_by)
INDEX(status)
INDEX(created_at)  -- للتقارير والفلترة
INDEX(type)        -- إذا كان يُستخدم في فلترة كثيرة
```

### ✅ ممنوع الحذف المباشر للبيانات المالية
```
✅ Soft Delete (deleted_at)
✅ Reverse Transaction
✅ Status = cancelled/reversed
❌ DELETE FROM transactions WHERE ...
```

### ✅ Status Field لكل كيان مهم
```php
// ✅ استخدم Enum واضح
enum InvoiceStatus: string {
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
```

### ✅ ممنوع تعديل Migrations بعد نشرها
بعد النشر → Migration جديد للتعديل:
```bash
# ✅ صح
php artisan make:migration add_tax_to_invoices_table

# ❌ غلط
# تعديل migration موجود يمس بيانات production
```

### ✅ Seeds يجب أن تكون Idempotent
```php
// ✅ صح — يمكن تشغيله مرات متعددة بأمان
Permission::firstOrCreate(['name' => 'invoices.create']);

// ❌ غلط — يرمي خطأ في التشغيل الثاني
Permission::create(['name' => 'invoices.create']);
```

---

## 4️⃣ قواعد APIs

### ✅ Versioning إلزامي
```
/api/v1/invoices
/api/v1/transactions
```
الـ version القديم يظل يعمل لمدة لا تقل عن **3 أشهر** بعد إصدار الجديد.

### ✅ Response Format موحد — دائماً
```json
// نجاح
{
  "status": true,
  "message": "تم بنجاح",
  "data": {},
  "meta": { "current_page": 1, "total": 100 },
  "auth": { "user": { "id": 1, "balance": 500.00 } }
}

// خطأ
{
  "status": false,
  "message": "رسالة الخطأ",
  "errors": {}
}
```

**استخدام دوال المساعدة دائماً:**
```php
return api_success($data);
return api_error($message);
return api_exception($e);
return api_forbidden($message);
return api_not_found($message);
```

### ✅ API Resources إلزامية — ممنوع إرجاع Model مباشرة
```php
// ✅ صح
return api_success(InvoiceResource::make($invoice));
return api_success(InvoiceResource::collection($invoices));

// ❌ غلط
return response()->json($invoice);
return response()->json($invoices->toArray());
```

### ✅ Form Request لكل Endpoint يستقبل بيانات
```php
// ✅ صح
public function store(CreateInvoiceRequest $request) { ... }

// ❌ غلط
public function store(Request $request) {
    $validated = $request->validate([...]); // Validation داخل Controller
}
```

### ✅ Pagination إلزامية للقوائم
```php
// ✅ صح
$invoices = Invoice::paginate(perPage: $request->per_page ?? 20);

// ❌ غلط — يُعيد كل السجلات
$invoices = Invoice::all();
```

### ✅ Rate Limiting للـ Endpoints الحساسة
```php
Route::middleware('throttle:login')->post('/login', ...);
Route::middleware('throttle:payments')->post('/transactions/deposit', ...);
```

### ✅ Idempotency للعمليات المالية
```
POST /api/v1/transactions/deposit
Header: Idempotency-Key: {uuid-v4}
```
نفس الـ Key → نفس النتيجة، لا تنفيذ مكرر.

---

## 5️⃣ قواعد الأمان

### ✅ ممنوع أي Route حساسة بدون حماية
```php
// ❌ خطر جداً — موجود حالياً ويجب إصلاحه
Route::get('/php/migrateAndSeed', ...); // بدون auth!

// ✅ صح
Route::middleware(['auth:sanctum', 'can:admin.super'])
    ->get('/php/migrate', ...);
```

### ✅ ممنوع if ($user->is_admin) للتحقق من الصلاحيات
```php
// ✅ صح — مرن وقابل للتوسع
if ($user->hasPermissionTo(perm_key('invoices.delete_all'))) { ... }
safeHasPermission($user, perm_key('balance.withdraw_any'))

// ❌ غلط — هش وغير قابل للتوسع
if ($user->is_admin || $user->role === 'manager') { ... }
```

### ✅ Company Scope على كل استعلام تابع لشركة
```php
// ✅ صح
Invoice::whereCompanyIsCurrent()->get();

// ❌ خطر — يُعيد بيانات شركات أخرى
Invoice::all();
```

### ✅ Secrets في ENV فقط — ممنوع في الكود
```php
// ✅ صح
config('services.stripe.secret')

// ❌ خطر
$stripeKey = 'sk_live_xxxxxxxxxxxxxxxxx'; // داخل الكود!
```

### ✅ تسجيل العمليات الحساسة في Audit Log
```php
// يجب تسجيل:
- تسجيل الدخول والخروج
- تعديل الصلاحيات
- العمليات المالية (يدوية خصوصاً)
- حذف البيانات
- تغيير كلمات المرور
- تغيير إعدادات الشركة
```

### ✅ Validate كل Input — حتى من المستخدم الـ Admin
```php
// ✅ دائماً افترض أن المدخلات غير موثوقة
$validated = $request->validated();
```

---

## 6️⃣ قواعد النظام المالي

### ✅ أي حركة مالية → سجل في Transactions
لا يوجد تغيير في الرصيد بدون سجل مقابل.

### ✅ ممنوع تعديل معاملة مالية — يُستخدم Reverse
```php
// ✅ صح
$transaction->reverse(); // يُنشئ معاملة عكسية

// ❌ غلط
$transaction->update(['amount' => 500]); // تعديل مباشر!
```

### ✅ balance_before و balance_after إلزاميان
```sql
-- كل transaction يجب أن يحتوي على:
balance_before DECIMAL(18,2) NOT NULL
balance_after  DECIMAL(18,2) NOT NULL
```

### ✅ Idempotency إلزامي للمدفوعات والتحويلات

### ✅ Audit Trail كامل لكل عملية مالية
يجب معرفة: من + متى + من أي IP + أي شركة + قبل/بعد.

### ✅ Double Entry (في المرحلة 3)
كل عملية مالية → قيد محاسبي مزدوج:
```
Debit Account X + Credit Account Y = 0 (دائماً يتوازن)
```

---

## 7️⃣ قواعد الأداء

### ✅ Queue للعمليات الثقيلة — إلزامي
```php
// ✅ صح — لا يوقف الـ Request
SendInvoiceEmailJob::dispatch($invoice)->onQueue('emails');
GeneratePdfJob::dispatch($invoice)->onQueue('documents');

// ❌ غلط — يوقف الـ Request لثوانٍ
Mail::to($customer)->send(new InvoiceMail($invoice));
```

**العمليات التي يجب أن تكون Queue:**
- PDF generation
- Email sending
- WhatsApp/SMS
- Excel Import/Export
- Backup operations
- Heavy reports
- Notification sending

### ✅ Eager Loading إلزامي — منع N+1
```php
// ✅ صح
Invoice::with(['customer', 'items', 'payments'])->paginate();

// ❌ غلط — N+1 queries
Invoice::paginate()->each(fn($i) => $i->customer->name);
```

**أداة الكشف:**
```php
// في التطوير
\DB::enableQueryLog();
// ثم فحص عدد الـ queries
```

### ✅ Cache للأجزاء الثقيلة
```php
// ✅ صح
$permissions = Cache::remember("user.{$id}.perms", 3600, fn() =>
    $user->getAllPermissions()
);

// إلزامي لـ:
- Dashboard stats
- Reports summary
- Permissions
- Settings
- Exchange rates
```

### ✅ Pagination دائماً للقوائم الكبيرة
```php
// ✅ صح
Invoice::paginate(20);

// ❌ خطر — يجلب الكل من DB
Invoice::all();
```

### ✅ ممنوع Queries داخل Loops
```php
// ✅ صح
$customers = Customer::whereIn('id', $ids)->get()->keyBy('id');
foreach ($invoices as $invoice) {
    $customer = $customers[$invoice->customer_id];
}

// ❌ غلط — N queries
foreach ($invoices as $invoice) {
    $customer = Customer::find($invoice->customer_id); // Query في كل iteration!
}
```

---

## 8️⃣ قواعد Event-Driven Architecture

### ✅ أي عملية رئيسية → Event
```php
// بعد كل عملية مهمة:
event(new InvoiceCreated($invoice));
event(new BalanceDeposited($user, $amount, $cashBox));
event(new InstallmentPaid($installment));
event(new TransactionReversed($original, $reversal));
```

### ✅ الأنظمة تتواصل عبر Events — لا ربط مباشر
```php
// ✅ صح — Decoupled
InvoiceService → fires InvoiceCreated event
AccountingListener → listens to InvoiceCreated → creates journal entry
NotificationListener → listens to InvoiceCreated → sends WhatsApp

// ❌ غلط — Tightly Coupled
InvoiceService::create() {
    $this->accountingService->createJournalEntry(); // ربط مباشر!
    $this->notificationService->sendWhatsApp();      // ربط مباشر!
}
```

### ✅ Listeners تعمل في Queue بشكل افتراضي
```php
class SendInvoiceNotification implements ShouldQueue {
    public string $queue = 'notifications';
}
```

---

## 9️⃣ قواعد الاختبارات

### ✅ Tests إلزامية لكل Feature حرجة
```
tests/
├── Feature/
│   ├── Finance/     ← أعلى أولوية
│   ├── Invoices/
│   ├── Auth/
│   └── Permissions/
└── Unit/
    ├── Services/
    └── DTOs/
```

### ✅ ممنوع Merge كود مالي بدون Tests تمر
```bash
# يجب أن يمر قبل أي Merge
php artisan test --testsuite=Feature
```

### ✅ Test Cases المطلوبة للماليات
```php
// كل test يغطي:
✅ الحالة الطبيعية (Happy Path)
✅ رصيد غير كافٍ
✅ خزينة غير موجودة
✅ مستخدم لا ينتمي للشركة
✅ تكرار العملية (Idempotency)
✅ عكس العملية
```

### ✅ Factory للـ Test Data
```php
// ✅ صح
$invoice = Invoice::factory()->forCompany($company)->create();

// ❌ غلط
$invoice = Invoice::create([...]); // بيانات هشة في الـ test
```

---

## 🔟 قواعد Logging والمراقبة

### ✅ Log levels صحيحة
```php
Log::debug()    // تفاصيل التطوير
Log::info()     // أحداث مهمة (تسجيل دخول، إنشاء فاتورة)
Log::warning()  // شيء غير متوقع لكن لم يفشل
Log::error()    // فشل عملية — يحتاج تدخل
Log::critical() // كارثة — تحتاج تدخل فوري
```

### ✅ Context كامل في Logs
```php
// ✅ صح — يساعد في debug الإنتاج
Log::error('فشل السحب', [
    'user_id'   => $userId,
    'amount'    => $amount,
    'cashbox_id'=> $cashBoxId,
    'company_id'=> $companyId,
    'error'     => $e->getMessage(),
    'trace'     => $e->getTraceAsString(),
]);

// ❌ غلط — عديم الفائدة
Log::error('فشل العملية');
```

### ✅ Monitoring إلزامي في Production
```
Sentry / Bugsnag  → Error tracking
Laravel Telescope → Development debugging
Laravel Horizon   → Queue monitoring
```

### ✅ Alerts للأحداث الحرجة
```
- 500 Error rate ارتفع
- Queue failed jobs تراكمت
- Balance discrepancy
- Unusual login activity
```

---

## 1️⃣1️⃣ قواعد Git Workflow

### ✅ Branch Strategy
```
main           → Production فقط (محمي)
develop        → Integration branch
feature/*      → ميزات جديدة
fix/*          → إصلاح bugs
hotfix/*       → إصلاح عاجل لـ Production
release/*      → تحضير إصدار
```

### ✅ Commit Messages واضحة (Conventional Commits)
```
# صيغة: type(scope): description

feat(invoices): add recurring invoice support
fix(transactions): prevent double withdrawal on timeout
refactor(auth): extract token validation to service
test(balance): add insufficient funds test case
docs(api): update deposit endpoint documentation
chore(deps): upgrade laravel to 11.x

# ❌ غلط
update
fix bug
wip
asdf
```

### ✅ ممنوع Push مباشر إلى main
كل شيء عبر Pull Request + Review.

### ✅ PR Requirements
```
✅ وصف واضح لماذا وماذا
✅ Tests تمر (CI يتحقق)
✅ لا Breaking Changes بدون توثيق
✅ Review من شخص آخر للكود الحرج
```

---

## 1️⃣2️⃣ قواعد التوثيق

### ✅ كل Module جديد يحتوي على README
```markdown
# Module Name

## Purpose (لماذا هذا الـ Module)
## Tables (الجداول والعلاقات)
## API Endpoints (مع أمثلة)
## Permissions (الصلاحيات المطلوبة)
## Events (ما يُطلق وما يستمع إليه)
## Business Rules (قواعد العمل الحرجة)
```

### ✅ توثيق القرارات المعمارية (ADR)
```
docs/decisions/
├── 001-use-cashbox-for-balance.md
├── 002-english-transaction-keys.md
└── 003-spatie-teams-for-multitenancy.md
```

### ✅ API Documentation تلقائي
```bash
php artisan scribe:generate
```
كل Endpoint يحتوي على: وصف + Parameters + Response examples + Permission required.

---

## ❌ ممنوعات صريحة (لا استثناء)

| ممنوع | البديل الصحيح |
|-------|--------------|
| Business Logic في Controller | Service / Action |
| `$request->all()` بدون validation | Form Request |
| إرجاع Model مباشرة | API Resource |
| `Invoice::all()` بدون pagination | `Invoice::paginate()` |
| `float` للأموال | `decimal(18,2)` |
| تعديل Transaction مباشرة | Reverse Transaction |
| `if ($user->is_admin)` | `hasPermissionTo()` |
| API Keys في الكود | ENV variables |
| Route حساسة بدون auth | middleware auth + permission |
| Queries داخل Loops | Eager Loading / whereIn |
| `try/catch` لإخفاء الأخطاء | Logging + إعادة الرمي |
| `Invoice::all()` في تقرير | Chunking + Queue |
| نسخ ولصق الكود | Extract to Service/Helper |
| جدول بدون `company_id` (إذا تابع لشركة) | إضافة الـ column |
| تعديل Migration منشورة | Migration جديد |
| Push إلى main مباشرة | Pull Request |
| Feature بدون Tests (حرجة) | Tests أولاً |
| `SQL` مباشر في Controller | Eloquent / Repository |

---

## ✅ ملخص سريع — Checklist قبل كل Merge

```
قبل رفع أي كود:

□ هل الـ Tests تمر؟  php artisan test
□ هل يوجد Validation لكل input؟
□ هل Response يستخدم api_success / api_error؟
□ هل يوجد Permission check؟
□ هل تم استخدام API Resource؟
□ هل العمليات المالية داخل DB::transaction()؟
□ هل الـ decimal(18,2) مستخدمة للأموال؟
□ هل العمليات الثقيلة في Queue؟
□ هل تم Eager Loading للعلاقات؟
□ هل يوجد company_id على الجداول التابعة؟
□ هل الـ Secrets في ENV وليس في الكود؟
□ هل يوجد Event للعملية الرئيسية؟
□ هل الـ Commit message واضح؟
□ هل الـ Migration لا تكسر الـ Production الحالي؟
```

---

*آخر تحديث: مايو 2026 — يجب مراجعة هذا الملف عند إضافة معايير جديدة*
