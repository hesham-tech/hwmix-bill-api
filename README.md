# HWNix Bill API — Backend Documentation

> **نظام فوترة متكامل متعدد الشركات** — Laravel 11 REST API  
> الدليل الشامل للمطورين وأنظمة الذكاء الاصطناعي

---

## 📋 نظرة عامة

**hwnix-bill-api** هو Backend API لنظام إدارة فواتير ومالية متكامل، مبني بـ **Laravel 11** مع دعم كامل لـ:
- تعدد الشركات (Multi-Tenant per User)
- إدارة الأرصدة والخزائن المالية
- الفواتير والأقساط والمدفوعات
- التقارير المالية الشاملة
- صلاحيات دقيقة مرتبطة بالشركة

---

## 🔧 المتطلبات والتقنيات

| التقنية | الإصدار |
|---------|---------|
| PHP | ^8.2 |
| Laravel | ^11.9 |
| MySQL | 8.x |
| Laravel Sanctum | ^4.0 (Token Auth) |
| Spatie Permission | ^6.10 (with Teams) |
| Laravel Reverb | ^1.0 (WebSockets) |
| Laravel Scout | ^10.15 (Search) |
| Maatwebsite Excel | ^3.1 (Export/Import) |
| DomPDF | ^3.1 (PDF Generation) |
| Spatie Backup | * |

---

## 🚀 التثبيت والإعداد

```bash
# 1. استنساخ المشروع
git clone <repo-url>
cd hwnix-bill-api

# 2. تثبيت الاعتماديات
composer install

# 3. إعداد ملف البيئة
cp .env.example .env
php artisan key:generate

# 4. إعداد قاعدة البيانات في .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hwnix_bill
DB_USERNAME=root
DB_PASSWORD=

# 5. تشغيل الـ Migrations
php artisan migrate

# 6. تشغيل الـ Seeders (الصلاحيات والمستخدم الافتراضي)
php artisan db:seed --class=PermissionsSeeder

# 7. تشغيل السيرفر
php artisan serve
```

**بيانات المدير الافتراضي:**
- Email: `admin@admin.com`
- Password: `12345678`

---

## 🏗️ هيكل المشروع

```
hwnix-bill-api/
├── app/
│   ├── Actions/          # Business logic actions
│   ├── Console/          # Artisan commands
│   ├── DTOs/             # Data Transfer Objects
│   ├── Helpers/          # Global helper functions
│   │   ├── response_helpers.php    # API response functions
│   │   ├── permissions_helpers.php # perm_key() helper
│   │   └── general_helpers.php
│   ├── Http/
│   │   ├── Controllers/  # 44+ controllers
│   │   ├── Middleware/   # Custom middlewares
│   │   └── Resources/    # API Resources (JSON transformers)
│   ├── Models/           # 57+ Eloquent models
│   ├── Observers/        # Model observers
│   ├── Services/         # Business service classes
│   └── Traits/           # Reusable traits
├── config/
│   └── permissions_keys.php  # ⭐ مصدر الحقيقة لجميع الصلاحيات
├── database/
│   ├── migrations/       # جميع migrations
│   └── seeders/          # Seeders
└── routes/
    └── api.php           # جميع API routes
```

---

## 🗄️ قاعدة البيانات — الجداول الرئيسية

### 👤 جداول المستخدمين والشركات

| الجدول | الوصف |
|--------|-------|
| `users` | المستخدمون الكلوبال (employees, customers, admins) |
| `companies` | الشركات |
| `company_user` | **Pivot** — ربط المستخدم بالشركة مع بيانات خاصة |
| `roles` | الأدوار (مرتبطة بـ company_id) |
| `permissions` | الصلاحيات |
| `model_has_permissions` | الصلاحيات المباشرة للمستخدم |
| `model_has_roles` | الأدوار المعينة للمستخدم |
| `role_company` | ربط الأدوار بالشركات |

**جدول `company_user` (Pivot مهم):**
```
id, company_id, user_id, created_by
role: enum[manager, employee, customer]
status: enum[active, pending, suspended]
timestamps
```
> ملاحظة: الأعمدة `nickname_in_company`, `full_name_in_company` موجودة في الكود لكن معطلة في الـ Migration حالياً.

---

### 💰 جداول الخزائن والمعاملات المالية

| الجدول | الوصف |
|--------|-------|
| `cash_boxes` | الخزائن النقدية (كل مستخدم له خزينة لكل شركة) |
| `cash_box_types` | أنواع الخزائن |
| `transactions` | سجل جميع المعاملات المالية |

**جدول `cash_boxes`:**
```
id, user_id, company_id, cash_box_type_id
name, balance (decimal)
is_default (boolean), is_active (boolean)
```

**جدول `transactions`:**
```
id, user_id, cashbox_id
target_user_id, target_cashbox_id (للتحويلات)
created_by, company_id
type: [deposit, withdraw, transfer_out, transfer_in, 
       reverse_deposit, reverse_withdraw, reverse_transfer, invoice]
amount, balance_before, balance_after
description
original_transaction_id (للمعاملات العكسية)
```

---

### 🧾 جداول الفواتير

| الجدول | الوصف |
|--------|-------|
| `invoices` | الفواتير الرئيسية |
| `invoice_items` | بنود الفاتورة (المنتجات/الخدمات) |
| `invoice_types` | أنواع الفواتير (بيع، شراء، مرتجع...) |
| `invoice_payments` | المدفوعات المرتبطة بالفاتورة |
| `payments` | المدفوعات العامة |
| `payment_methods` | طرق الدفع |

---

### 📦 جداول المنتجات والمخزون

| الجدول | الوصف |
|--------|-------|
| `products` | المنتجات |
| `product_variants` | المتغيرات (ألوان، مقاسات...) |
| `product_variant_attributes` | سمات المتغيرات |
| `attributes` | تعريف السمات (لون، مقاس...) |
| `attribute_values` | قيم السمات (أحمر، كبير...) |
| `categories` | التصنيفات الهرمية |
| `brands` | العلامات التجارية |
| `stocks` | حركة المخزون |
| `warehouses` | المستودعات |

---

### 📅 جداول الأقساط

| الجدول | الوصف |
|--------|-------|
| `installment_plans` | خطط التقسيط |
| `installments` | الأقساط الفردية |
| `installment_payments` | مدفوعات الأقساط |
| `installment_payment_details` | تفاصيل المدفوعات |

---

### 📊 جداول أخرى

| الجدول | الوصف |
|--------|-------|
| `expenses` | المصروفات |
| `expense_categories` | تصنيفات المصروفات |
| `revenues` | الإيرادات |
| `profits` | الأرباح |
| `financial_ledger` | دفتر الأستاذ العام |
| `activity_logs` | سجل الأنشطة (Audit Trail) |
| `tasks` / `task_groups` | إدارة المهام |
| `subscriptions` | الاشتراكات |
| `services` | الخدمات |
| `plans` | خطط الأسعار |
| `error_reports` | تقارير الأخطاء |
| `backups` | النسخ الاحتياطية |

---

## 🔐 نظام الصلاحيات

يستخدم المشروع **Spatie Laravel Permission** مع **Teams Mode** مفعّل، حيث `team_id = company_id`.

### مصدر الصلاحيات

الملف `config/permissions_keys.php` هو **المرجع الوحيد الرسمي** لجميع مفاتيح الصلاحيات.

```php
// مثال على الصلاحيات
perm_key('users.view_all')      // عرض جميع المستخدمين
perm_key('balance.withdraw_any') // سحب من أي حساب
perm_key('admin.super')         // مدير عام
perm_key('admin.company')       // مدير شركة
```

### مجموعات الصلاحيات

| المجموعة | الوصف |
|----------|-------|
| `admin.*` | صلاحيات المديرين (super, company) |
| `users.*` | إدارة المستخدمين |
| `balance.*` | العمليات المالية (deposit, withdraw, transfer + _any) |
| `transactions.*` | إدارة المعاملات |
| `invoices.*` | إدارة الفواتير |
| `products.*` | إدارة المنتجات |
| `reports.*` | التقارير |
| `installments.*` | الأقساط |
| `cash_boxes.*` | الخزائن |
| `roles.*` | الأدوار |
| `...` | وغيرها (~25 مجموعة) |

### أنواع الأفعال لكل مجموعة

```
page, view_all, view_children, view_self
create
update_all, update_children, update_self
delete_all, delete_children, delete_self
```

### التحقق من الصلاحيات بأمان

```php
// ✅ دائماً استخدم safeHasPermission() داخل TransactionController
// لتجنب PermissionDoesNotExist Exception
safeHasPermission($user, perm_key('balance.withdraw_any'))

// ✅ أو عبر hasPermissionTo العادية في باقي الكود
$user->hasPermissionTo(perm_key('invoices.create'))
```

---

## 💡 الـ Traits الأساسية

### `ManagesBalance` — إدارة الأرصدة

الـ Trait الأهم في النظام، مُضاف على موديل `User`:

```php
// السحب
$user->withdraw(float $amount, ?int $cashBoxId, ?string $description)

// الإيداع  
$user->deposit(float $amount, ?int $cashBoxId, ?string $description)

// التحويل للمستخدم نفسه (قديم)
$user->transfer($cashBoxId, $targetUserId, $amount)

// التحويل المباشر بين خزينتين (الأحدث)
$user->transferTo(User $target, float $amount, int $fromBoxId, int $toBoxId)
```

**قواعد الخزينة:**
1. كل مستخدم له خزينة واحدة أو أكثر لكل شركة
2. تُحدد الخزينة الافتراضية بـ `is_default = true`
3. إذا لم توجد خزينة افتراضية → يبحث عن أي خزينة نشطة `is_active = true`
4. يتم تسجيل كل عملية في جدول `transactions`

### `Filterable` — الفلترة

يُضيف دعم الفلترة الديناميكية على أي حقل بشكل موحد.

### `Scopes` — النطاقات العامة

Scopes مُشتركة لفلترة البيانات (by company, by creator, etc.)

### `FilterableByCompany`

يُقيّد استعلامات الموديل تلقائياً بالشركة النشطة للمستخدم.

### `LogsActivity`

يُسجّل تلقائياً جميع عمليات الإنشاء والتعديل والحذف في `activity_logs`.

### `HasImages`

يُضيف علاقة Polymorphic مع جدول `images`.

### `SmartSearch`

يُضيف قدرات بحث ذكية عبر Scout.

---

## 🌐 API Endpoints

### 🔓 Public Routes
```
POST   /api/register
POST   /api/login
POST   /api/error-reports
GET    /api/media/view/{path}
```

### 🔒 Protected Routes (require Sanctum token)

#### المصادقة
```
POST   /api/logout
GET    /api/me
GET    /api/auth/check
GET    /api/auth/sessions
DELETE /api/auth/sessions/{id}
DELETE /api/auth/sessions-others
```

#### المستخدمون
```
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
POST   /api/users/delete
GET    /api/users/lookup
GET    /api/users/stats
GET    /api/users/search
PUT    /api/change-company/{user}
PUT    /api/users/{user}/cashbox/{id}/set-default
```

#### المعاملات المالية
```
POST   /api/transactions/deposit
POST   /api/transactions/withdraw
POST   /api/transactions/transfer
GET    /api/transactions
GET    /api/transactions/user/{cashBoxId?}
POST   /api/transactions/{id}/reverse
```

#### الفواتير
```
GET    /api/invoices
POST   /api/invoices
GET    /api/invoices/{id}
PUT    /api/invoices/{id}
DELETE /api/invoices/{id}
GET    /api/invoice/{id}/pdf
GET    /api/invoice/{id}/pdf-data
POST   /api/invoice/{id}/email-pdf
POST   /api/invoices/export-excel
```

#### التقارير
```
GET    /api/reports/sales
GET    /api/reports/sales/top-products
GET    /api/reports/sales/top-customers
GET    /api/reports/sales/trend
GET    /api/reports/profit-loss
GET    /api/reports/profit-loss/monthly-comparison
GET    /api/reports/stock
GET    /api/reports/stock/valuation
GET    /api/reports/stock/low-stock
GET    /api/reports/cash-flow
GET    /api/reports/cash-flow/by-cash-box
GET    /api/reports/cash-flow/summary
GET    /api/reports/tax
GET    /api/reports/customers/top
GET    /api/reports/customers/debts
```

#### المنتجات والمخزون
```
GET/POST       /api/products
GET/PUT/DELETE /api/products/{id}
GET            /api/products/export
POST           /api/products/import
GET/POST       /api/product-variants
GET/POST       /api/stocks
GET/POST       /api/warehouses
GET/POST       /api/categories
GET/POST       /api/brands
GET/POST       /api/attributes
GET/POST       /api/attribute-values
```

#### الأقساط
```
GET/POST       /api/installment-plans
GET/POST       /api/installments
GET/POST       /api/installment-payments
POST           /api/installment-payments/pay
GET/POST       /api/installment-payment-details
```

#### الماليات
```
GET/POST       /api/expenses
GET/POST       /api/expense-categories
GET/POST       /api/revenues
GET/POST       /api/profits
GET            /api/financial-ledger
POST           /api/financial-ledger/export
GET/POST       /api/payments
GET/POST       /api/payment-methods
```

#### الإدارة
```
GET/POST       /api/roles
POST           /api/roles/assign
GET/POST       /api/companies
GET/POST       /api/cash-boxes
GET/POST       /api/cash-box-types
GET/POST       /api/invoice-types
GET            /api/permissions
GET/POST       /api/backups
GET/POST       /api/tasks
GET/POST       /api/task-groups
GET/POST       /api/services
GET/POST       /api/subscriptions
GET/POST       /api/plans
GET            /api/dashboard/summary
GET            /api/global-search
```

---

## 📦 شكل الاستجابة الموحدة

يستخدم المشروع Helper Functions موحدة للاستجابات:

```php
// ✅ نجاح
api_success($data, 'رسالة نجاح', 200)
// الناتج:
{
  "status": true,
  "message": "رسالة نجاح",
  "data": [...],
  "auth": { "user": { "id": 1, "balance": 1500.00 } },
  // للقوائم المُقسمة:
  "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 100 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}

// ❌ خطأ
api_error('رسالة الخطأ', [], 400)
// الناتج:
{ "status": false, "message": "رسالة الخطأ", "errors": [] }

// 💥 Exception
api_exception($e)

// 🔐 غير مصرح
api_unauthorized('يجب تسجيل الدخول')  // 401

// 🚫 ممنوع
api_forbidden('لا توجد صلاحية')        // 403

// 🔍 غير موجود
api_not_found('السجل غير موجود')       // 404
```

---

## 🏦 آلية عمل الخزائن والأرصدة

```
User (global)
  └── CashBox (per company, per user)
         └── is_default: true  ← الخزينة الرئيسية للشركة
         └── balance: decimal  ← الرصيد الفعلي
         └── company_id        ← تعدد الشركات

// balance = sum of cashboxes.balance for active company
User::getBalanceAttribute() → getDefaultCashBoxForCompany(company_id)->balance
```

---

## 🔄 أنواع المعاملات المالية

| النوع | الوصف | يُنشئ في DB |
|-------|-------|------------|
| `deposit` | إيداع نقدي | سجل واحد |
| `withdraw` | سحب نقدي | سجل واحد |
| `transfer_out` | تحويل صادر | سجلان (out + in) |
| `transfer_in` | تحويل وارد | (مُنشأ مع transfer_out) |
| `invoice` | دفع فاتورة | سجل واحد |
| `reverse_deposit` | عكس إيداع | سجل عكسي |
| `reverse_withdraw` | عكس سحب | سجل عكسي |
| `reverse_transfer` | عكس تحويل | سجل عكسي |

---

## 👁️ الـ Observers

| المراقب | الموديل | الوظيفة |
|---------|---------|---------|
| `UserObserver` | `User` | مزامنة البيانات مع company_user عند التعديل |
| `CompanyUserObserver` | `CompanyUser` | إنشاء خزينة افتراضية عند إضافة مستخدم لشركة |

---

## 🔧 الـ Middleware المهمة

| Middleware | الوظيفة |
|-----------|---------|
| `auth:sanctum` | التحقق من التوثيق |
| `ScopePermissionsByCompany` | تقييد الصلاحيات بنطاق الشركة النشطة |

---

## 📊 نظام التقارير

### متحكمات التقارير (`app/Http/Controllers/Reports/`)

| المتحكم | التقارير |
|---------|---------|
| `SalesReportController` | المبيعات، أفضل المنتجات، أفضل العملاء، الاتجاه |
| `ProfitLossReportController` | الأرباح والخسائر، مقارنة شهرية |
| `StockReportController` | المخزون، التقييم، المخزون المنخفض |
| `CashFlowReportController` | التدفق النقدي، الملخص، الاتجاه |
| `TaxReportController` | الضرائب المُحصّلة والمدفوعة |
| `CustomerSupplierReportController` | أفضل العملاء، الديون |

---

## 🌱 الـ Seeders

| Seeder | الوظيفة |
|--------|---------|
| `PermissionsSeeder` | يحذف ويُعيد إنشاء جميع الصلاحيات من `permissions_keys.php` ويُشغّل `RolesAndPermissionsSeeder` |
| `RolesAndPermissionsSeeder` | يُنشئ/يُحدث المستخدم الإداري (`admin@admin.com`) ويمنحه جميع الصلاحيات |
| `DatabaseSeeder` | يُشغّل جميع الـ Seeders الأساسية |

**تحديث الصلاحيات في production:**
```bash
php artisan db:seed --class=PermissionsSeeder
# أو عبر API (بدون auth):
GET /php/PermissionsSeeder
```

---

## ⚡ الـ Artisan Routes المخصصة

```
GET /php/migrate            → تشغيل migrations
GET /php/migrateAndSeed     → migrate:fresh + seed
GET /php/PermissionsSeeder  → تحديث الصلاحيات
GET /php/seedRolesAndPermissions
GET /php/clear              → مسح جميع الكاشات
GET /php/generateBackup     → نسخة احتياطية
GET /php/runComposerDump    → composer dump-autoload
```

> ⚠️ هذه الروابط **بدون حماية** — يجب تأمينها في production.

---

## 🏢 نموذج تعدد الشركات (Multi-Tenancy)

```
المستخدم الواحد يمكن أن يكون:
  - موظفاً في شركة A (role: employee)
  - عميلاً في شركة B (role: customer)
  - مديراً في شركة C (role: manager)

الشركة النشطة تُحدد بـ: Auth::user()->company_id
الصلاحيات مُقيدة بـ: ScopePermissionsByCompany middleware
```

---

## 📝 Helper Functions المهمة

```php
// الصلاحيات
perm_key('users.view_all')           // يُرجع المفتاح من config
safeHasPermission($user, $perm)      // تحقق آمن لا يرمي Exception

// الاستجابات
api_success($data, $message, $code)
api_error($message, $errors, $code)
api_exception($throwable, $code)
api_not_found($message)
api_unauthorized($message)
api_forbidden($message)
api_no_content($message)
```

---

## 📡 WebSockets (Laravel Reverb)

يستخدم المشروع **Laravel Reverb** للاتصال الفوري (Real-time).

```bash
php artisan reverb:start
```

---

## 🔍 البحث (Laravel Scout)

مُفعّل عبر `SmartSearch` trait على الموديلات الرئيسية.

```
GET /api/global-search?q=keyword
```

---

## 🚨 نقاط يجب معرفتها للمطورين

1. **الأعمدة المعلّقة في `company_user`**: `nickname_in_company`, `full_name_in_company`, `user_phone`, `user_email`, `user_username` — معرّفة في الكود لكن معطّلة في الـ Migration. الكود يتحقق من وجودها قبل استخدامها.

2. **أنواع المعاملات**: دائماً استخدم المفاتيح الإنجليزية (`deposit`, `withdraw`, `transfer_out`) — النظام القديم كان يستخدم العربية ولا يزال يدعمها للتوافق العكسي.

3. **`safeHasPermission()`**: استخدمها بدلاً من `hasPermissionTo()` في `TransactionController` لتجنب `PermissionDoesNotExist` Exception عند نقص الصلاحيات من DB.

4. **الخزينة الافتراضية**: دائماً يتم جلبها عبر `getDefaultCashBoxForCompany($companyId)` وليس مباشرة من `balance` column في `users`.

5. **الصلاحيات Teams**: `setPermissionsTeamId($companyId)` يجب استدعاؤه قبل أي عملية sync للصلاحيات.

6. **API Response**: كل استجابة ناجحة تحتوي على `auth.user.balance` — الرصيد المحدّث للمستخدم الحالي.

---

## 📮 تنسيق طلبات API

### Headers المطلوبة
```
Authorization: Bearer {sanctum_token}
Accept: application/json
Content-Type: application/json
```

### مثال طلب إيداع
```json
POST /api/transactions/deposit
{
  "user_id": 5,
  "amount": 1000,
  "description": "إيداع رصيد أولي",
  "cashbox_id": 3  // اختياري
}
```

### مثال طلب تحويل
```json
POST /api/transactions/transfer
{
  "from_user_id": 1,
  "target_user_id": 5,
  "from_cashbox_id": 2,
  "to_cashbox_id": 8,
  "amount": 500,
  "description": "تحويل داخلي"
}
```

---

## 🗂️ ملفات الإعداد المهمة

| الملف | الوظيفة |
|-------|---------|
| `config/permissions_keys.php` | تعريف جميع مفاتيح الصلاحيات |
| `config/permission.php` | إعداد Spatie Permission (teams: true) |
| `config/sanctum.php` | إعداد التوثيق |
| `config/scout.php` | إعداد البحث |
| `.env` | إعدادات البيئة |

---

*آخر تحديث: مايو 2026*
