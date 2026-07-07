# ARCHITECTURE_REVIEW.md — النسخة 2
# مراجعة المعمارية + خطة عمل محكمة — نظام HWNix ERP

> **التاريخ:** 30 يونيو 2026
> **الحالة:** خطة عمل محكمة — معتمدة على أرقام فعلية من قاعدة البيانات
> **بيئة التطوير:** Local (تجريبية) | الإنتاج: على الاستضافة

---

## 0. حقائق البيانات الفعلية (ليست نظرية)

| الجدول | العدد | ملاحظة مهمة |
|---|---|---|
| `users` | 109 | — |
| `companies` | 13 | — |
| `branches` | 14 | — |
| `cash_boxes` | 365 | 101 في شركة واحدة! |
| `invoices` | 110 | 87 تقسيط، 12 شراء، 11 بيع |
| `transactions` | 320 | deposit(206) + withdraw(114) فقط |
| `installment_payments` | 227 | — |
| `invoice_payments` | 6 | ضئيل جداً |
| `financial_ledger` | 46 | لا يُستخدم فعلياً |
| `revenues` | **0** | الجدول فارغ تماماً |
| `profits` | **0** | الجدول فارغ تماماً |
| `user_company_cash` | **0** | الجدول فارغ — مرشح للحذف |
| `stats_users_summary` | **0** | الجدول فارغ |
| `company_user.role` | 127 كلهم = customer | لا manager ولا employee |
| `transactions.source_invoice_id` | **0 مرتبط** | الحقل موجود لكن غير مستخدم |
| `transactions.target_user_id` | **0 مرتبط** | التحويلات بين الخزائن غير مستخدمة |

---

## 1. المشكلة الحرجة الموجودة الآن في البيانات

### 20 خزنة بأرصدة غير متطابقة مع transactions

الفحص الفعلي كشف أن:
- **17 خزنة:** رصيد مخزون بينما transactions = 0 (رصد بدون سجل)
- **3 خزائن:** فرق جزئي (#1257: فرق 29,465 | #1258: 830 | #1259: 1,027)
- **الخزنة #8:** رصيد -113,423 مع صفر transactions

**السبب المرجح:** الخزائن القديمة (قبل نظام التسجيل) تم إدخال رصيدها مباشرة.
**هذا يعني:** `cash_boxes.balance` ليس مصدر حقيقة موثوقاً الآن.

---

## 2. ملاحظات على الغياب الكامل في بعض الجداول

| الجدول | الغياب | التفسير |
|---|---|---|
| `revenues`, `profits` | 0 سجل | لا يُملآن من الكود الحالي — تقارير الإيرادات تعتمد على `invoices` مباشرة |
| `financial_ledger` | 46 فقط | يُملأ جزئياً — غير متكامل مع بقية الأحداث |
| `transactions.source_invoice_id` | 0 مرتبط | الحقل أُضيف لكن لا يُملأ بعد |
| `user_company_cash` | 0 | جدول أُنشئ وتُرك — يجب حذفه |

---

## 3. تحليل المشاكل المعمارية (مع أدلة رقمية)

### 3.1 الخزنة مرتبطة بـ user_id — المشكلة موجودة فعلاً

**الدليل الرقمي:**
- 365 خزنة — كلها مرتبطة بـ user_id (user_id لا يساوي NULL في أي منها)
- شركة #19 لديها 101 خزنة — بمعنى 101 مستخدم كل منهم له خزنة
- `AccountingService::recordPayment` يبحث عن الخزنة عبر `user_id` أولاً

**الأثر:** لا يمكن إنشاء خزنة شركة مستقلة بدون مستخدم.

### 3.2 الأدوار — كلهم customer

**الدليل الرقمي:**
- 127 سجل في company_user — كلهم role = 'customer'
- لا يوجد ولو سجل واحد: manager, employee, supplier, partner

**الأثر:** الموردون والشركاء غير موجودين في البيانات أصلاً.

### 3.3 السجل المالي غير متكامل

**الدليل الرقمي:**
- 320 transaction | لكن source_invoice_id = 0 في كلهم
- 110 فاتورة موجودة لكن لا يوجد رابط بينها وبين transactions

**الأثر:** لا يمكن تتبع أي معاملة إلى فاتورتها المصدر.

---

## 4. ما يعمل صحيحاً (محمي من التغيير)

| ما يعمل | الدليل |
|---|---|
| نظام الفواتير والأقساط | 110 فاتورة + 227 قسط — يعمل بشكل صحيح |
| نظام installment_payments | 227 سجل — مكتمل |
| Multi-Tenant بـ company_id | CompanyScope يعمل على كل استعلام |
| Permission System (Spatie) | لا مشاكل |
| نظام الفروع | 14 فرع — يعمل |
| Soft Deletes للفواتير | موجود |

---

## 5. خطة العمل المحكمة (مع معايير قبول وقياس نجاح)

> **المبدأ:** كل مرحلة = مجموعة تغييرات + معيار قبول محدد + rollback plan + لا تنفيذ بدون اجتياز المعيار السابق.

---

### المرحلة 0 — إكمال الاستقرار والتوثيق

**الحالة الحالية:** منجزة 20% فقط (التوثيق الهيكلي موجود، الـ Flows والـ Tests غائبة)

**الأهداف:**

**0.1 توثيق الـ Flows الحرجة (خطوة بخطوة):**
توثيق 4 Flows في ملف `FINANCIAL_FLOWS.md`:

```
Flow 1: إنشاء فاتورة بيع مع دفعة جزئية
  الخطوات:
  1. InvoiceController::store() يستدعي InvoiceService
  2. InvoiceService ينشئ Invoice
  3. AccountingService::recordInvoiceCreation() يُستدعى
  4. party->deposit(netAmount) → يبحث عن cash_box بـ user_id → يحدث balance
  5. recordPayment() → staff->deposit(paidAmount) → خزنة الموظف تتغير
  6. Transaction يُسجَّل (deposit)
  7. Transaction آخر يُسجَّل (withdraw)
  النقاط الحرجة: إذا كان العميل cash_customer → skip_party_balance = true

Flow 2: إلغاء فاتورة بيع مدفوعة جزئياً
Flow 3: تحصيل دفعة من عميل (collectPayment)
Flow 4: إنشاء فاتورة شراء (purchase)
```

**0.2 كتابة Regression Tests (الشبكة الأمان):**

اختبارات إلزامية قبل أي تغيير:
- `test_invoice_creation_updates_cashbox_balance()` — التحقق من أن إنشاء فاتورة يغير الرصيد بالقيمة الصحيحة
- `test_invoice_creation_for_cash_customer_does_not_update_party_balance()` — العميل النقدي لا يُمس رصيده
- `test_invoice_cancellation_reverses_cashbox_balance()` — الإلغاء يعكس الرصيد
- `test_installment_payment_reduces_cashbox_balance()` — دفع قسط يؤثر على الخزنة
- `test_multi_tenant_isolation()` — شركة لا ترى بيانات شركة أخرى
- `test_cashbox_balance_matches_transaction_sum()` — رصيد الخزنة = SUM(transactions) بعد أي عملية

**معيار قبول المرحلة 0:**
- كل الـ 6 tests تجتاز على بيئة نظيفة
- ملف `FINANCIAL_FLOWS.md` مكتمل بالـ 4 Flows
- لا يوجد test يتوقف على بيانات hardcoded

**Rollback Plan:** لا حاجة — لا تغيير في الكود

**الوقت المقدر:** 1-2 أسبوع

---

### المرحلة 1 — إصلاح مشكلة التطابق وتنظيف البيانات الميتة

**الهدف:** إصلاح المشكلة الموجودة الآن في البيانات + حذف الجداول الميتة

**1.1 فهم سبب التطابق في البيانات الحقيقية (على الإنتاج):**

قبل التنفيذ، يجب تشغيل فحص على بيئة الإنتاج لمعرفة:
- كم خزنة لديها نفس مشكلة التطابق؟
- ما مصدر الرصيد الأصلي؟ (رصيد افتتاحي يدوي؟ هجرة بيانات قديمة؟)

**1.2 المعالجة:**
- إنشاء Command: `php artisan cashbox:reconcile --dry-run` أولاً
- يعرض الفروق بدون تعديل
- بعد المراجعة: `php artisan cashbox:reconcile --fix` يُنشئ transaction افتتاحية بالفرق

**1.3 حذف الجداول الميتة:**
- `user_company_cash` (0 سجل — لا يُستخدم)
- `stats_users_summary`, `stats_products_summary` (0 سجل)
- `revenues`, `profits` (0 سجل — إذا تأكدنا أنها ليست مستخدمة)

**معيار قبول المرحلة 1:**
- جميع الـ 6 tests من المرحلة 0 لا تزال تجتاز
- لا توجد خزنة بفرق رصيد > 0.01 بعد الـ reconcile
- الجداول الميتة محذوفة من migration بدون كسر أي query حالية

**Rollback Plan:** كل حذف جدول = migration له `down()` يعيد الجدول

**الوقت المقدر:** 1 أسبوع

---

### المرحلة 2 — إصلاح ربط transactions بالفواتير

**الهدف:** ملء `source_invoice_id` في transactions الجديدة فقط (بدون تعديل القديمة)

**المشكلة المكتشفة:** 320 transaction، لكن source_invoice_id = 0 في كلها رغم وجود 110 فاتورة.

**الخطوات:**
- تعديل `AccountingService::recordInvoiceCreation()` لتمرير `source_invoice_id` في كل Transaction جديدة
- تعديل `recordPayment()` لقبول `source_invoice_id` من الـ options
- لا تعديل للبيانات القديمة — فقط الجديدة تُملأ

**معيار قبول المرحلة 2:**
- كل فاتورة جديدة تُنشئ transactions مرتبطة بـ source_invoice_id
- test: `test_invoice_creates_linked_transactions()`
- لا regression في الـ 6 tests القديمة

**Rollback Plan:** تراجع commit في git — لا migration تحتاج rollback

**الوقت المقدر:** 3-5 أيام

---

### المرحلة 3 — توسعة نموذج الأدوار

**الهدف:** السماح للشخص الواحد بأن يكون عميلاً وموردًا في نفس الوقت

**الخطوات:**

**3.1 جدول party_roles (إضافة فقط — لا حذف):**
```
party_roles {
    id, company_id, user_id,
    role ENUM(customer, employee, supplier, partner, shareholder),
    metadata JSON nullable,  -- للبيانات الإضافية حسب الدور
    is_active boolean,
    created_by, timestamps
}
```

**3.2 Backward Compatibility:**
- عند إنشاء سجل في company_user → Observer ينشئ تلقائياً سجل في party_roles
- الكود الجديد يقرأ من party_roles
- الكود القديم يعمل من company_user.role بدون تعديل

**3.3 معيار القبول الصريح:**
- الـ Observer يعمل: إنشاء company_user يُنشئ party_roles
- تعديل party_roles لا يُعدّل company_user (one-way sync فقط)
- test: `test_party_can_have_multiple_roles_in_same_company()`
- test: `test_supplier_role_does_not_affect_customer_invoices()`

**Rollback Plan:**
- حذف Observer
- حذف migration الجدول الجديد
- لا تأثير على الكود القديم

**الوقت المقدر:** 2-3 أسابيع

---

### المرحلة 4 — فصل الخزائن عن المستخدمين

**الهدف:** الخزنة تصبح وعاء مستقل محكوم بالصلاحيات

**التحضير الإلزامي قبل البدء:**
- يجب إكمال المرحلتين 0 و 1 أولاً
- الـ Regression Tests يجب أن تجتاز جميعها على الإنتاج

**الخطوات:**

**4.1 تعديل cash_boxes:**
```
ALTER TABLE cash_boxes
    MODIFY user_id INT UNSIGNED NULL,    -- من NOT NULL إلى nullable
    ADD COLUMN access_type ENUM('user_owned', 'company_shared', 'branch_shared') DEFAULT 'user_owned'
```

**4.2 جدول cash_box_permissions (اختياري في البداية):**
```
cash_box_permissions {
    id, cash_box_id, user_id,
    permission_level ENUM(view, deposit, withdraw, manage),
    company_id, created_by, timestamps
}
```

**4.3 تعديل AccountingService::recordPayment():**
استبدال البحث عبر user_id بـ:
```
1. إذا cash_box_id صريح → استخدمه
2. إذا لا → ابحث عن خزنة مملوكة للمستخدم (user_owned)
3. إذا لا → ابحث عن خزنة مشتركة في الفرع (branch_shared)
4. إذا لا → استثناء واضح مع رسالة خطأ
```

**معيار قبول المرحلة 4:**
- الـ 6 Regression Tests تجتاز بدون تعديل
- test: `test_cashbox_can_exist_without_user_id()`
- test: `test_shared_cashbox_accessible_to_permitted_users()`
- لا يوجد استعلام يفشل بسبب user_id = NULL

**KPIs:**
- وقت استجابة إنشاء فاتورة: لا يزيد أكثر من 20% عن الحالي
- لا deadlocks في الاختبارات المتزامنة

**Rollback Plan:**
- `ALTER TABLE cash_boxes MODIFY user_id INT UNSIGNED NOT NULL` — يمكن التراجع بسرعة
- حذف migration بـ `down()` فقط إذا user_id لا يزال NOT NULL في كل السجلات الحالية

**الوقت المقدر:** 3-4 أسابيع

---

### المرحلة 5 — نموذج العلاقات المالية مع الأشخاص

**الهدف:** جدول واضح لـ "ما للشخص عند الشركة" و"ما على الشخص للشركة"

**الخطوات:**

**5.1 جدول party_financial_balances:**
```
party_financial_balances {
    id, company_id, user_id,
    relation_type ENUM(receivable, payable, advance, custody, capital_share),
    balance DECIMAL(18,2),
    last_transaction_id nullable,
    notes, created_by, timestamps
}
```

**5.2 ملء الأرصدة التاريخية:**
- Command: `php artisan financial:migrate-balances --dry-run` أولاً
- يحسب الرصيد من invoices لكل عميل
- يكتبه في party_financial_balances

**5.3 السلف والعهد للموظفين:**
- `employee_advances` جدول مستقل (advance_type, amount, status, repaid_amount)

**معيار قبول المرحلة 5:**
- رصيد كل عميل في party_financial_balances = SUM(net_amount - paid_amount) من invoices
- test: `test_customer_balance_matches_invoice_balance()`
- لا regression في الـ 6 tests القديمة

**Rollback Plan:**
- party_financial_balances يُقرأ منه فقط في واجهات جديدة
- الكود القديم لا يُمس حتى يتأكد من الصحة

**الوقت المقدر:** 3-4 أسابيع

---

### المرحلة 6 — التنظيف والتوحيد

**الهدف:** حذف كل ما أصبح قديماً

**المهام:**
- حذف company_user.role بعد ترحيل كامل لـ party_roles
- حذف منطق skip_party_balance
- توحيد الـ balance reporting

**الوقت المقدر:** 2 أسابيع

---

## 6. تعريف النجاح لكل مرحلة (Acceptance Criteria)

| المرحلة | معيار النجاح الإلزامي | KPI |
|---|---|---|
| **0** | 6 Regression Tests تجتاز | وقت التنفيذ < 30 ثانية |
| **1** | 0 خزنة بفرق رصيد > 0.01 | — |
| **2** | كل فاتورة جديدة = transactions مرتبطة | — |
| **3** | شخص واحد يحمل 2 أدوار بدون تكرار | — |
| **4** | خزنة بدون user_id تعمل في جميع العمليات | وقت الفاتورة ≤ +20% |
| **5** | رصيد العميل في الجدول = رصيد الفواتير | — |
| **6** | لا كود يشير لجداول محذوفة | — |

---

## 7. ترتيب الأولويات النهائي

```
الآن فوراً → المرحلة 0 (Flows + Tests)
ثم         → المرحلة 1 (إصلاح التطابق + تنظيف الجداول الميتة)
ثم         → المرحلة 2 (ربط transactions بالفواتير)
ثم         → المرحلة 3 (الأدوار المتعددة — عند الحاجة فعلياً)
ثم         → المرحلة 4 (فصل الخزائن — الأعلى مخاطرة)
ثم         → المرحلة 5 (العلاقات المالية — استثمارية)
أخيراً    → المرحلة 6 (التنظيف)
```

**الإجمالي التقديري:** 15-18 أسبوع

---

## 8. قواعد صارمة لكل مرحلة

1. لا تبدأ مرحلة قبل اجتياز معيار قبول المرحلة السابقة
2. كل migration لها `down()` مكتوب ومختبر
3. كل تغيير في قاعدة البيانات = اختبار من النوع `test_rollback_migration()`
4. الإنتاج لا يُلمس إلا بعد نجاح كامل في Local + Staging
5. لا حذف بيانات من الإنتاج — فقط إضافة أو تعديل
6. push للـ git = اجتياز `php artisan test` بلا أخطاء

---

*تم إعداد هذا الملف بتاريخ 30 يونيو 2026 — يستبدل النسخة الأولى بعد إضافة أرقام فعلية ومعايير قبول صريحة.*
