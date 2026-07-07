# FINANCIAL_FLOWS.md
# توثيق التدفقات المالية التفصيلية (Flows) — نظام HWNix ERP

> **الحالة:** معتمد كجزء من المرحلة 0 (الاستقرار والتوثيق).
> **الملفات المعنية:** `AccountingService.php`, `ManagesBalance.php`, `Transaction.php`, `Invoice.php`.

---

## 1. Flow 1: إنشاء فاتورة مبيعات مع دفعة جزئية (Create Invoice with Partial Payment)

يحدث هذا التدفق عند تسجيل فاتورة جديدة (سواء كانت مبيعات عادية `sale` أو مبيعات بالتقسيط `installment_sale` أو فاتورة خدمات `service_invoice`) مع دفع جزء من قيمتها نقدًا.

### الخطوات البرمجية خطوة بخطوة:

1. **الاستدعاء الأساسي:**
   يستقبل الـ Controller طلب إنشاء الفاتورة، ويمررها بعد الحفظ الأولي (حيث تُنشأ الفاتورة بحالة `confirmed`) إلى `AccountingService::recordInvoiceCreation($invoice, $options)`.
2. **بدء المعاملة (Database Transaction):**
   يلف التابع كامل العمليات داخل `DB::transaction()` لضمان الاتساق.
3. **التحقق من العميل النقدي الافتراضي (Cash Customer):**
   - يجلب التابع العميل: `$party = User::withoutGlobalScopes()->find($invoice->user_id);`.
   - يتحقق مما إذا كان العميل هو العميل النقدي الافتراضي للشركة: `$isCashCustomer = $party && $party->isDefaultCashCustomer($invoice->company_id);`.
4. **تسجيل المديونية (لغير العملاء النقديين):**
   - إذا كان العميل حقيقياً (وليس العميل النقدي الافتراضي)، يسجل التابع كامل القيمة الصافية كمديونية عبر:
     ```php
     $party->deposit($netAmount, $userCashBoxId, "إثبات مديونية فاتورة {$type} رقم: {$invoice->invoice_number}");
     ```
   - *ملاحظة:* استدعاء `$party->deposit` يقوم بالتأثير على خزنة العميل الافتراضية، ويسجل معاملة من نوع `deposit` باسم العميل.
5. **تسجيل الدفعة النقدية (في خزنة الموظف):**
   إذا كان هناك مبلغ مدفوع (`paidAmount > 0`)، يتم استدعاء التابع `recordPayment()` كالتالي:
   ```php
   $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
       'cash_box_id' => $cashBoxId,
       'party_cash_box_id' => $userCashBoxId,
       'description' => "دفعة من فاتورة رقم: {$invoice->invoice_number}",
       'skip_party_balance' => $isCashCustomer, // يمنع تعديل رصيد العميل النقدي
   ]);
   ```
6. **معالجة رصيد الخزائن داخل `recordPayment`:**
   - يجلب التابع الخزنة النشطة/الافتراضية للموظف (`$staffBox`) وللعميل (`$partyBox`).
   - يعدل رصيد خزنة الموظف: `$staffBox->balance += $amount;` (عبر `$staff->deposit`).
   - إذا لم يكن العميل نقدياً (`$skipPartyBalance === false`)، يعدل رصيد خزنة العميل: `$partyBox->balance -= $amount;` (عبر `$party->withdraw`).
   - يسجل ذلك معاملتين في جدول `transactions` (واحدة للموظف إيداع `deposit` وواحدة للعميل سحب `withdraw`).
7. **توزيع المبالغ الزائدة (Excess Payment):**
   - إذا كان العميل حقيقياً، وكان المدفوع أكبر من صافي قيمة الفاتورة الحالي (`paidAmount > netAmount`)، يتم استقطاع الزيادة وتوزيعها آلياً لتسديد الفواتير القديمة غير المدفوعة أو المدفوعة جزئياً لنفس العميل بترتيب المعرفات تصاعدياً (`lockForUpdate`).

---

## 2. Flow 2: إلغاء الفاتورة مع رد الدفعة (Cancel Invoice & Refund)

يحدث هذا التدفق عند إلغاء الفاتورة بالكامل وعكس أثرها المالي.

### الخطوات البرمجية خطوة بخطوة:

1. **الاستدعاء الأساسي:**
   يستدعي النظام `AccountingService::reverseInvoice($invoice, $options)`.
2. **إلغاء المديونية (عكس الأثر للمبيعات):**
   - إذا كان العميل حقيقياً وليس عميلاً نقدياً:
     ```php
     $party->withdraw($netAmount, $userCashBoxId, "إلغاء مديونية فاتورة {$type} رقم: {$invoice->invoice_number}");
     ```
   - إذا كان العميل نقدياً: يتم تسجيل معاملة توثيقية فقط من نوع `invoice_cancel` بمبلغ الفاتورة الإجمالي دون تعديل أي خزنة:
     ```php
     Transaction::create([... 'type' => 'invoice_cancel', 'amount' => $netAmount, 'balance_before' => 0, 'balance_after' => 0]);
     ```
3. **رد المبلغ المدفوع (Refund Payment):**
   إذا كان هناك مبلغ مدفوع مسبقاً (`paidAmount > 0`):
   ```php
   $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
       'cash_box_id' => $cashBoxId,
       'party_cash_box_id' => $userCashBoxId,
       'description' => "رد مبلغ مدفوع لإلغاء الفاتورة رقم: {$invoice->invoice_number}",
       'skip_party_balance' => $isCashCustomer,
       'source_invoice_id'  => $invoice->id,
   ]);
   ```
   - يتم سحب المبلغ من خزنة الموظف/الشركة (`out`) وإعادته لخزنة العميل (إن لم يكن نقدياً).

---

## 3. Flow 3: تحصيل دفعة من حساب عميل (Collect Payment / Pay Account)

يحدث هذا التدفق عند استلام دفعة مالية من العميل دون ارتباط مباشر بفاتورة مبيعات محددة لحظة الدفع (دفعة على الحساب)، ليتم تحصيلها وتوزيعها تلقائياً على فواتيره المستحقة القديمة.

### الخطوات البرمجية خطوة بخطوة:

1. **الاستدعاء الأساسي:**
   يتم استدعاء `AccountingService::collectPayment($staff, $party, $amount, $options)`.
2. **تسجيل إيداع الدفعة في خزنة الشركة/الموظف:**
   ```php
   $staff->deposit($amount, $cashBoxId, "تحصيل نقدي من {$party->name} - {$notes}");
   ```
3. **جلب الفواتير المستحقة (Due Invoices):**
   يتم جلب فواتير العميل التي حالتها `unpaid` أو `partially_paid` غير الملغاة مرتبة تصاعدياً من الأقدم للأحدث مع قفل السطور للتحيين بأمان:
   ```php
   $dueInvoices = Invoice::where('user_id', $party->id)->whereIn('payment_status', ...)->lockForUpdate()->get();
   ```
4. **توزيع الدفعة:**
   - يمر التابع بحلقة تكرار على الفواتير، ويقوم بتسديد المتبقي منها واحدة تلو الأخرى.
   - لكل فاتورة يتم تسديد جزء منها، يتم إنشاء سجل دفع `InvoicePayment` وتحديث مبالغ وحالة الفاتورة (`paid_amount`, `remaining_amount`, `payment_status`).
5. **خصم المبلغ من مديونية العميل:**
   بعد التوزيع، يُخصم كامل المبلغ من ذمة العميل (عبر سحب من خزنته الافتراضية):
   ```php
   $party->withdraw($amount, $partyCashBoxId, "سداد مبلغ: {$amount} - {$notes}");
   ```

---

## 4. Flow 4: إنشاء فاتورة شراء (Create Purchase Invoice)

يمثل تدفق تسجيل فواتير الشراء من الموردين لتسجيل الالتزامات والمدفوعات الصادرة.

### الخطوات البرمجية خطوة بخطوة:

1. **إثبات الالتزام (لغير العملاء/الموردين النقديين):**
   - إذا كان المورد حقيقياً، يُسجل النظام الالتزام المالي بسحب القيمة من رصيده (مما يجعله دائناً بالصافي):
     ```php
     $party->withdraw($netAmount, $userCashBoxId, "إثبات التزام فاتورة شراء رقم: {$invoice->invoice_number}");
     ```
2. **تسجيل الدفعة الصادرة (الصرف النقدي):**
   - إذا تم دفع جزء من الفاتورة للمورد (`paidAmount > 0`)، يتم صرفه من خزنة الشركة عبر `recordPayment` بالاتجاه الصادر `out`:
     ```php
     $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
         'cash_box_id' => $cashBoxId,
         'party_cash_box_id' => $userCashBoxId,
         'description' => "سداد فاتورة شراء رقم: {$invoice->invoice_number}",
         'skip_party_balance' => $isCashCustomer,
     ]);
     ```
   - يقلل هذا رصيد خزنة الشركة بالدفع، ويزيد رصيد المورد (يقلل التزامه تجاه الشركة).

---

## 5. النقاط الحرجة والمخاطر المحددة (Critical Points & Risks)

- **الاعتماد الكلي على الخزنة الافتراضية:** في حال لم يكن للمستخدم (سواء كان موظفًا أو عميلاً) خزنة معلمة كـ `is_default` أو `is_active` في نفس الشركة، فإن العمليات المالية ستفشل بالكامل وتلقي استثناءً يمنع إكمال الفاتورة.
- **تكرار العمليات وتحديث الرصيد المباشر:** بما أن حقول الرصيد في جدول `cash_boxes` تُحدَّث عبر العمليات المباشرة (`increment` / `decrement`)، فإن أي انقطاع في المعاملة قبل كتابة الـ Transaction يُسبب عدم تطابق الأرصدة (المشكلة الفعلية المكتشفة).
