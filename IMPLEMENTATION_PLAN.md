# 🗺️ خطة التنفيذ الهندسية — HWNix Bill ERP Platform

> [!IMPORTANT]
> **⛔ لا يتم تنفيذ أي مرحلة إلا بعد الحصول على موافقة صريحة.**
> **⛔ يجب تحديث ملف [TASKS.md](file:///d:/Dev/projects/hwnix-bill-api/TASKS.md) بما تم إنجازه بعد الانتهاء من كل خطوة.**
> هذا الملف للتوثيق والتخطيط الاستراتيجي فقط.

---

## 🧭 الفلسفة العامة وقواعد التصميم

تعتمد هذه الخطة على رؤية وكيل الذكاء الاصطناعي كـ **Senior Software Architect** لبناء نظام ERP احترافي قابل للتوسع (Scalable) ومستقر (Stable).

| القاعدة | التفاصيل التقنية |
| :--- | :--- |
| **Modular & Domain Driven** | تقسيم المشروع إلى Business Domains واضحة وليس مجرد جداول. |
| **Event-Driven Architecture** | الاعتماد على الأحداث (Events) لتقليل التشابك (Decoupling). |
| **Multi-Tenant Ready** | عزل كامل للبيانات (Isolation) ودعم `tenant_id` و `branch_id` في جميع العمليات. |
| **Active Branch Context** | نظام فروع متكامل يدعم صلاحيات وعمليات مستقلة لكل فرع. |
| **Zero Downtime** | الحفاظ على الاستقرار و Backward Compatibility لـ APIs القديمة. |
| **Feature Flagged**     | التحكم في تفعيل وتعطيل الميزات من الداشبورد (Modules Control). |
| **Global-Ready**        | التعامل مع التوقيت العالمي (UTC) والعملات المتعددة كمعيار أساسي. |
| **Search Optimized**    | محرك بحث فائق السرعة يعتمد على Indexing وليس SQL فقط. |

---

## 📐 الهيكل الموديولي المستقبلي (Modular Architecture)

سيتم تقسيم المشروع إلى **Core Domains** و **Feature Modules** و **Shared Kernel**.

```
app/
├── Modules/
│   ├── Core/                → Shared Kernel: BaseDTO, BaseAction, Helpers, Traits
│   ├── System/              → Health, Backups, Feature Flags, Media Manager
│   ├── Auth/                → Sessions, 2FA, Permissions (Scoped by Company/Branch)
│   ├── Companies/           → Multi-company management & Branch System (Full)
│   ├── Users/               → User management & Staff Roles
│   ├── Finance/             → Cashboxes, Transactions, Multi-Currency, Payments Layer
│   ├── Accounting/          → Journal, Ledger, Double entry, Fiscal Years
│   ├── Invoices/            → Invoices, Recurring, PDF, Taxes
│   ├── Inventory/           → Products, Stocks, Warehouses (Multi-branch), Transfers
│   ├── Suppliers/           → Suppliers, Purchases, Goods Receipts
│   ├── CRM/                 → Customers, Loyalty & Rewards, Leads, Quotations
│   ├── Store/               → E-commerce Readiness, Orders, Shipping, Coupons
│   ├── Marketing/           → Pixels, Conversion APIs, UTM Tracking
│   ├── Notifications/       → Multi-channel (WhatsApp, SMS, Email, Push)
│   ├── Subscriptions/       → SaaS Plans, Billing, Usage Limits, Affiliates
│   ├── POS/                 → Fast sales, Device management, Offline Sync
│   ├── AI/                  → Forecasting, Insights, Anomaly Detection
│   ├── Audit/               → Professional Activity Logs (Audit Trail)
│   ├── Integrations/        → Webhooks, SDK, External API Services
│   └── Reports/             → Centralized Export/Import Engine
```

---

## 🚀 المرحلة 0 — Stabilization & Infrastructure Layer

> **الأولوية: الأعلى | التأسيس التقني الصحيح**

### 0.1 — نظام الفروع الأساسي (Core Branch System)
* تجهيز المشروع لدعم `branch_id` في جميع الجداول الأساسية.
* إنشاء Middleware لتحديد `Active Branch Context`.
* دعم عزل البيانات (Branch Isolation) برمجياً.

### 0.2 — تأمين Artisan Routes & API Versioning
* حماية جميع مسارات التحكم (`/php/*`) بصلاحيات `super-admin`.
* تفعيل API Versioning (`/api/v1`) لضمان عدم كسر الأنظمة الحالية.

### 0.3 — نظام المهام الخلفية (Queue System)
* تفعيل **Redis** كـ Queue Driver.
* تحويل العمليات الثقيلة (PDF, Emails, Exports, Backups) إلى Background Jobs.
* تثبيت `laravel/horizon` لمراقبة أداء المهام.

### 0.4 — نظام الأحداث (Event-Driven Foundation)
* البدء في توليد Events للعمليات الحساسة (InvoicePaid, BalanceChanged).
* استخدام Listeners للقيام بالعمليات الجانبية (Side Effects).

### 0.5 — تطوير نظام السجلات (Professional Audit Logs)
* تسجيل العمليات بتفاصيل (المستخدم، الشركة، الفرع، العملية، القيم قبل وبعد، IP، الجهاز).
* تسجيل "الحدث المسؤول" وليس فقط تغيير القيم.

### 0.6 — مراقبة الأخطاء وتوثيق الـ API
* دمج **Sentry** أو **Bugsnag** لتتبع الأخطاء في الوقت الفعلي (Real-time Error Tracking).
* استخدام **Scribe** لتوليد توثيق API تفاعلي ومحدث دائماً.

### 0.8 — نظام التوقيت العالمي (UTC Strategy)
* اعتماد تخزين جميع التواريخ في DB بصيغة UTC.
* إنشاء Helpers لتحويل التوقيت حسب Timezone الشركة/الفرع عند العرض.

### 0.9 — تأمين الـ API و Rate Limiting
* تطبيق `Dynamic Rate Limiting` حسب نوع المستخدم وخطة الاشتراك.
* حماية الـ Endpoints الحساسة بـ `Throttle Middleware`.

---

## 🚀 المرحلة 1 — Core Refactoring & Modularization

### 1.1 — تقسيم النظام إلى Modules
* استخدام `nwidart/laravel-modules` لإدارة الموديولات.
* إنشاء **Shared Kernel** (Core Module) يحتوي على الـ Base Classes والـ Traits المشتركة.

### 1.2 — استكمال نظام الفروع بالكامل
* كل فرع يمتلك: موظفين، مخازن، خزائن، وفواتير مستقلة.
* صلاحيات المستخدمين تُحدد على مستوى الفرع (Branch-based Permissions).
* لوحة تحكم للمدير لمراجعة أداء جميع الفروع أو فرع محدد.

### 1.3 — استراتيجية الفهرسة (Database Indexing Strategy)
* إضافة Indexes للحقول المتكررة في الاستعلامات: `tenant_id`, `branch_id`, `company_id`, `status`.
* تحسين أداء الاستعلامات الضخمة لضمان سرعة النظام مع نمو البيانات.

### 1.4 — نظام الـ DTOs و Actions
* منع وجود Business Logic داخل الـ Controllers.
* استخدام DTOs لنقل البيانات و Services/Actions لتنفيذ المنطق البرمجي.

### 1.5 — التحكم في ظهور المنتجات (Visibility Controls)
* إضافة حقول `is_active_in_store` و `is_active_in_sales` لكل منتج.
* ضمان عزل المنتجات برمجياً حسب القناة (Channel Isolation).

---

## 🚀 المرحلة 2 — Commercial Readiness & Service Layer

### 2.1 — طبقة بوابات الدفع (Payment Gateway Layer)
* استخدام **Strategy Pattern** لدعم مزودين متعددين (Stripe, Paymob, Fawry, Moyasar).
* إدارة الإعدادات (API Keys, Sandbox/Live) من الداشبورد.
* توحيد الاستجابة (Normalized Response) ودعم Webhooks Idempotency.

### 2.2 — نظام مزودات البريد الديناميكي (Dynamic Email System)
* إدارة مزودات البريد (SMTP, Mailgun, AWS SES) من الداشبورد.
* دعم اختبار الاتصال (Connection Test) وتغيير المزود لحظياً.

### 2.3 — مركز التنبيهات الموحد (Notification Center)
* دعم قنوات متعددة: WhatsApp (Cloud API), SMS (Twilio/Unifonic), Push, In-App.
* نظام خرائط المتغيرات (Variable Mapper) للقوالب.
* دعم Failover و Retry Strategy و Delivery Logs.

### 2.4 — محرك التصدير والاستيراد (Export/Import Engine)
* محرك موحد يدعم Excel/CSV للملفات الكبيرة عبر Queues.
* تتبع حالة العملية (Progress Bar) وإرسال إشعار عند الانتهاء.

### 2.5 — مدير الوسائط المركزي (Media Manager)
* نظام مركزي يدعم S3/Cloudinary/Local.
* ضغط الصور تلقائياً ودعم WebP وتغيير الأحجام (Resizing).

---

## 🚀 المرحلة 3 — Advanced Accounting Layer

### 3.1 — المحاسبة المزدوجة (Double-Entry System)
* دليل حسابات (COA) مرن وقابل للتخصيص.
* محرك الترحيل (Posting Engine) يولد قيوداً تلقائية من الأحداث (Events).

### 3.2 — السنوات والفترات المالية
* دعم إغلاق الفترات المالية (Period Locking) ومنع التعديل على البيانات القديمة.

---

## 🚀 المرحلة 4 — Inventory & Multi-Branch Logistics

### 4.1 — إدارة المخازن المتقدمة
* التحويلات بين المخازن والفروع (Warehouse Transfers).
* نقاط إعادة الطلب (Reorder Levels) وتنبيهات نقص المخزون.

### 4.2 — المشتريات والموردين
* أوامر الشراء (PO) وإدارة استلام البضائع (Goods Receipts).

---

## 🚀 المرحلة 5 — Store & CRM Excellence

### 5.1 — Loyalty & Rewards System
* نظام نقاط مرن (Event Driven).
* تحكم في قيمة النقاط، استثناء منتجات، وسجل كامل لكل عميل.

### 5.2 — Store Readiness (E-commerce)
* دعم الطلبات، الشحن، الكوبونات، وتتبع حالة الشحنات.

### 5.3 — Marketing & Tracking Layer (Marketing Hub)
* دمج Pixels (Meta, TikTok, Google, Snapchat).
* دعم **Conversion APIs (Server-Side)** لضمان دقة التتبع.
* تتبع الـ UTM و Attribution لمعرفة مصدر المبيعات.

---

## 🚀 المرحلة 6 — POS & Offline Operations

* نظام جلسات POS (Opening/Closing Sessions).
* مزامنة البيانات عند انقطاع الإنترنت (Offline Sync).
* إدارة الأجهزة والطابعات.

---

## 🚀 المرحلة 7 — SaaS, Enterprise & Growth

### 7.1 — نظام اشتراكات SaaS المتكامل
* خطط (Plans)، دورات فوترة، فترات تجريبية، وحدود استخدام (Usage Limits).
* الفصل التام بين الصلاحيات (Permissions) والوصول للميزات (Feature Access).

### 7.2 — Feature Flags System (Modules Control)
* تفعيل وتعطيل الموديولات والميزات لكل شركة أو خطة بشكل مستقل.

### 7.4 — محرك البحث العالمي (Global Search Engine)
* دمج **Meilisearch** لتوفير بحث فوري (Lightning-fast) في المنتجات، الفواتير، والعملاء.
* تحديث الـ Indexes تلقائياً عبر `Model Observers`.

### 7.5 — معمارية التوسع (Read/Write Splitting)
* تجهيز النظام لدعم العمل على Database Clusters (Master for Writes, Slaves for Reads).
* توجيه التقارير الثقيلة للمستودع التحليلي (Read Replica).

---

## 🚀 المرحلة 8 — AI & Intelligent ERP

* توقعات التدفق النقدي (Cash Flow Forecasting).
* المساعد الذكي (AI Assistant) للتقارير والتحليلات.
* كشف العمليات المشبوهة (Anomaly Detection).

---

## 🚀 المرحلة 9 — Ecosystem & Integration First

* تفعيل Webhooks للأطراف الخارجية.
* إطلاق SDK للمطورين وبناء Marketplace للملحقات.

---

## 📌 قواعد التنفيذ والالتزام الصارم

* **Multi-Tenant First**: أي كود جديد يجب أن يحترم خصوصية الشركة والفرع.
* **No Logic in Controllers**: الكنترولر للاستقبال والإرسال فقط.
* **Tests Mandatory**: أي ميزة مالية أو حرجة يجب أن تحتوي على اختبارات (Automated Tests).
* **Documentation**: تحديث ملفات التوثيق والـ API docs مع كل مرحلة.
* **Security**: جميع العمليات الحساسة يجب أن تُسجل في Audit Logs.
* **Money**: استخدام `decimal(18,2)` دائماً، ممنوع `float` أو `double`.

---

## 🏁 خريطة التحول (Transformation Roadmap)

| بنهاية المرحلة | الحالة الناتجة للنظام |
| :--- | :--- |
| **0 & 1** | نظام آمن، Modular، يدعم الفروع والشركات بكفاءة عالية. |
| **2** | نظام تجاري جاهز للبيع (Payment, Email, Notification, Media). |
| **3 & 4** | ERP متكامل (محاسبة، مخازن، مشتريات). |
| **5 & 6** | منصة مبيعات ذكية (CRM, Loyalty, Store, POS). |
| **7 & 8 & 9** | منصة SaaS عالمية مدعومة بالذكاء الاصطناعي و Ecosystem متكامل. |

---

_آخر تحديث: مايو 2026 | بواسطة: وكيل الذكاء الاصطناعي (Senior Architect)_
