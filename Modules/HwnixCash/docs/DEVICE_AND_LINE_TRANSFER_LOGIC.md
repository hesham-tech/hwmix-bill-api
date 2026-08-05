# وثيقة المعمارية: منطق نقل الأجهزة وشرائح الاتصال بين الشركات (Device & Line Transfer Architecture)

---

## 📌 1. الفكرة والهدف المعماري

في تطبيق **HWNix Cash ERP**، قد ينتقل الهاتف المحمول المستعمل (المُعرف بـ `android_id`) من شركة لشركة أخرى في حالتين أساسيتين:

1. **البيع المباشر للهاتف (دون الخطوط)** (`transfer_mode = device_only`):
   - تبيع الشركة الأولى الهاتف لشركة أخرى وتصادف وجود شرائح اتصال مخصصة للشركة الأولى لم تُنقل معه.
   - يتم نقل ملكية الهاتف (`HwnixCashDevice`) للشركة الجديدة.
   - تُفصل جميع الخطوط السابقة عن الهاتف بجعل `device_android_id = null` وتحديث حالتها إلى `unlinked`.
   - تُحفظ كل سجلات المعاملات والرسائل القديمة تحت الشركة الأولى بدون المساس بها.

2. **تبديل الشركة للهاتف والخطوط معاً (الافتراضي)** (`transfer_mode = with_lines`):
   - يغير المستخدم/الوكيل الشركة النشطة في التطبيق ومعه نفس شرائح الاتصال في الهاتف.
   - يتم نقل الهاتف (`HwnixCashDevice`) والشرائح المربوطة به (`HwnixCashLine`) والمحافظ المالية المربوطة بتلك الشرائح (`HwnixCashFinancialAccount`) تلقائياً للشركة الجديدة داخل `DB::transaction`.

---

## 🏗️ 2. مكونات المنظومة في الكود

- **الهجرة (`Migration`)**:
  - `2026_08_05_000002_make_device_android_id_nullable_in_hwnix_cash_lines_table.php`:
  - جعلت حقل `device_android_id` في جدول `hwnix_cash_lines` قابلاً لاستقبال `null` لتمكين فك ارتباط الخطوط عن الأجهزة المباعة بأمان دون تسبب في أخطاء قاعدة البيانات (MySQL 1048 Constraint Violation).

- **مستودع الأجهزة (`EloquentHwnixCashDeviceRepository.php`)**:
  - دالة `createOrUpdate(DeviceData $dto, int $companyId, int $userId)` تقوم بتنفيذ النقل التلقائي بناءً على خاصية `$dto->transferMode`.
  - تتم العملية برمتها داخل transaction مالية موحدة.

- **مزامنة الشرائح (`SyncSimLinesAction.php`)**:
  - عند المزامنة، يتم ربط خطوط الجهاز النشطة بالشركة الجديدة وتحديث `company_id` دون الإضرار بالخطوط غير المرتبطة.

- **طلب تسجيل الجهاز (`RegisterDeviceRequest.php`) & `DeviceData.php`**:
  - حقل اختياري `transfer_mode` بقيم:
    - `with_lines` (الافتراضي)
    - `device_only`

---

## ⚙️ 3. كيفية التعديل المستقبلي إذا تغيرت متطلبات العمل

إذا رغبت في تغيير سلوك نقل الأجهزة مستقبلاً:
1. لتعديل سلوك المسارات: راجع كلاس `Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashDeviceRepository.php` داخل دالة `createOrUpdate`.
2. إذا أردت أرشفة الخطوط بحذف مرن بدلاً من جعل `device_android_id = null` عند البيع: يمكنك تغيير دالة التحديث للخطوط في `device_only` إلى `$line->delete()`.
