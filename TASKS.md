# ✅ Task List — HWNix Bill ERP Transformation

> [!IMPORTANT]
> **الحالة الحالية: جاهز للبدء 🚀**
> يجب تحديث هذا الملف بعد كل خطوة تنفيذية لضمان تتبع التقدم بدقة.
> راجع [IMPLEMENTATION_PLAN.md](file:///d:/Dev/projects/hwnix-bill-api/IMPLEMENTATION_PLAN.md) للتفاصيل المعمارية.

---

## 🚀 المرحلة 0 — Stabilization & Infrastructure Layer
*الهدف: تأمين النظام الحالي وتجهيز الأساس التقني العالمي.*

### 0.1 تأمين Artisan Routes
- [x] إضافة middleware للتحقق من الصلاحية على `/php/*` routes.
- [x] تفعيل صلاحية `admin.super` للوصول لهذه المسارات (تم القفل ببريد مدير النظام).
- [x] اختبار أن الروابط لا تعمل بدون توثيق (Sanctum).

### 0.2 نظام الفروع الأساسي (Core Branch System)
- [x] إنشاء جدول `branches` وموديل `Branch`.
- [x] إضافة `branch_id` للجداول المالية والأساسية (Users, Invoices, CashBoxes, Transactions, Warehouses, Expenses, Ledger).
- [x] إنشاء `BranchContextMiddleware` لتحديد الفرع النشط من الهيدر `X-Branch-Id`.
- [x] تطبيق `FilterableByBranch` trait على الموديلات لعزل البيانات تلقائياً.

### 0.3 نظام التوقيت العالمي (UTC Strategy)
- [x] مراجعة ملفات الإعداد لضبط `timezone` على `UTC` (تم التحقق: مضبوط على UTC).
- [x] التأكد من تخزين التواريخ في قاعدة البيانات بصيغة UTC.
- [x] إنشاء Helpers لتحويل التوقيت عند العرض للمستخدم حسب منطقته الزمنية.

### 0.4 تأمين الـ API و Rate Limiting
- [x] إعداد `RateLimiter` في `AppServiceProvider`.
- [x] تطبيق Middleware `throttle` على جميع مسارات الـ API (60 طلب/دقيقة للـ API، 5 للـ Auth).

### 0.5 المهام الخلفية (Queue System)
- [x] إعداد Redis في ملف `.env` (تم تحويل QUEUE_CONNECTION إلى redis).
- [x] تثبيت وتجهيز Laravel Horizon للمراقبة.
- [x] تأمين لوحة تحكم Horizon لمدير النظام فقط.
- [x] تحويل (Email, PDF, Notifications) لتستخدم الـ Jobs.

### 0.6 مراقبة الأخطاء وتوثيق الـ API
- [x] إعداد Scribe للتوثيق التلقائي (تم النشر والإعداد).
- [x] تفعيل نظام `ErrorReport` لاستقبال تقارير الأخطاء من الواجهة الأمامية.

### 0.7 نظام الإجراءات المتكررة (Idempotency)
- [x] إنشاء `IdempotencyMiddleware` لحماية العمليات المالية.
- [x] إضافة عمود `idempotency_key` للجداول المالية.
- [x] تفعيل Middleware عالمياً على الـ API.

---

## 🚀 المرحلة 1 — Core Refactoring & Modularization
*الهدف: تحويل المشروع إلى بنية موديولية احترافية.*

### 1.1 هيكلة الموديولات (Modular Setup)
- [ ] تثبيت `nwidart/laravel-modules`.
- [ ] إنشاء **Core Module** (Shared Kernel).
- [ ] إنشاء ملفات القاعدة (BaseDTO, BaseAction, BaseService).

### 1.2 استكمال نظام الفروع (Full Branches System)
- [ ] بناء CRUD الفروع وإدارة صلاحيات الموظفين لكل فرع.
- [ ] اختبار عزل البيانات (Branch Isolation) بين مستخدمين من فروع مختلفة.

### 1.3 استراتيجية الفهرسة (Database Indexing)
- [ ] إضافة Indexes للحقول: `tenant_id`, `branch_id`, `status`, `created_at`.

### 1.4 التحكم في ظهور المنتجات (Visibility Controls)
- [ ] إضافة حقول `is_active_in_store` و `is_active_in_sales` لجدول المنتجات.
- [ ] تعديل الـ Queries الخاصة بالمتجر لتشمل المنتجات المفعلة للمتجر فقط.
- [ ] تعديل الـ Queries الخاصة بالـ POS/Sales لتشمل المنتجات المفعلة للمبيعات فقط.

---

## 🚀 المرحلة 2 — Commercial Readiness Layer
*الهدف: جعل النظام قابلاً للبيع والتشغيل التجاري الواسع.*

### 2.1 طبقة الدفع (Payment Gateway Layer)
- [ ] تطبيق Strategy Pattern لبوابات الدفع.
- [ ] مكاملة Stripe أو Paymob كأول بوابة.

### 2.2 نظام التنبيهات والبريد (Service Providers)
- [ ] بناء نظام إدارة مزودات البريد (SMTP, Mailgun, etc) من الداشبورد.
- [ ] إعداد مركز التنبيهات (WhatsApp, SMS, Email).

### 2.3 محرك التصدير والاستيراد (Export/Import Engine)
- [ ] بناء المحرك الموحد الذي يعمل في الخلفية (Queues).

### 2.4 مدير الوسائط (Media Manager)
- [ ] إعداد نظام رفع الملفات المركزي مع ضغط الصور التلقائي.

---

## 🚀 المرحلة 3 — Advanced Accounting Layer
*الهدف: تحويل النظام إلى ERP مالي حقيقي.*

### 3.1 القيود المحاسبية (Double-Entry)
- [ ] بناء دليل الحسابات (COA) ومحرك الترحيل التلقائي.
- [ ] إعداد التقارير المالية (Balance Sheet, Income Statement).

---

## 🚀 المرحلة 4 — Inventory & Multi-Branch Logistics
*الهدف: إدارة المخزون والمشتريات عبر فروع متعددة.*

### 4.1 إدارة المخازن والمشتريات
- [ ] بناء نظام التحويلات بين المخازن (Warehouse Transfers).
- [ ] نظام أوامر الشراء واستلام البضائع.

---

## 🚀 المرحلة 5 — Store & CRM Excellence
*الهدف: تحويل النظام إلى منصة مبيعات وتسويق ذكية.*

### 5.1 نظام الولاء (Loyalty System)
- [ ] بناء محرك النقاط والمكافآت (Event Driven).

### 5.2 التتبع التسويقي (Marketing Hub)
- [ ] دمج Pixels و Conversion APIs (Meta, TikTok, Google).
- [ ] نظام تتبع الـ UTM ومصدر المبيعات.

---

## 🚀 المرحلة 6 — POS & Offline Operations
*الهدف: دعم البيع السريع ونقاط البيع.*

### 6.1 نظام الـ POS
- [ ] إدارة الجلسات (Sessions) والمزامنة عند انقطاع الإنترنت (Offline Sync).

---

## 🚀 المرحلة 7 — SaaS, Enterprise & Growth
*الهدف: التوسع إلى منصة SaaS عالمية.*

### 7.1 نظام الاشتراكات و Feature Flags
- [ ] بناء نظام الخطط وحدود الاستخدام والتحكم في الميزات.

### 7.2 محرك البحث العالمي (Global Search)
- [ ] مكاملة **Meilisearch** للبحث اللحظي في كامل النظام.

### 7.3 جاهزية التوسع (Read/Write Splitting)
- [ ] إعداد النظام لدعم فصل قواعد بيانات القراءة عن الكتابة.

---

## 🚀 المرحلة 8 — AI & Intelligent ERP
*الهدف: تقديم رؤى ذكية باستخدام الذكاء الاصطناعي.*

---

## 🚀 المرحلة 9 — Ecosystem Layer
*الهدف: بناء مجتمع المطورين والمكاملات الخارجية.*

---

## 🏁 ملخص التقدم

| المرحلة | الحالة | النسبة |
| :--- | :--- | :--- |
| **0 - Stabilization** | ⏳ في الانتظار | 0% |
| **1 - Modularization** | ⏳ في الانتظار | 0% |
| **2 - Commercial** | ⏳ في الانتظار | 0% |
| **باقي المراحل** | ⏳ في الانتظار | 0% |

---

_آخر تحديث: مايو 2026_
