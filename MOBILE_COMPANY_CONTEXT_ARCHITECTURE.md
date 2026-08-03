# Mobile Company Context Architecture (HWNix ERP)

## 📌 مقدمة (Introduction)
في بيئة (SaaS Multi-Tenant)، يحتاج النظام لمعرفة "الشركة النشطة" (Active Company) التي يعمل عليها المستخدم حالياً.
سابقاً، كان النظام يعتمد بالكامل على متغير `$user->active_company_id` المخزن في قاعدة البيانات. هذا الأسلوب ممتاز لتطبيق الويب، لكنه يسبب مشكلة لتطبيقات الموبايل (أو الـ API الخارجية) حيث يمكن للمستخدم إرسال طلبات لشركات مختلفة دون الحاجة إلى تغيير `active_company_id` الخاص به في كل مرة، ولتجنب الـ Race Conditions إذا كان المستخدم فاتحاً أكثر من شركة في نفس الوقت (عبر أجهزة مختلفة أو نوافذ مختلفة).

تم بناء معمارية **Mobile Company Context** لحل هذه المشكلة عبر تمرير سياق الشركة المطلوبة ديناميكياً مع كل طلب (Request).

---

## 🏗️ مكونات المعمارية (Architecture Components)

### 1. `CurrentCompanyResolver` (محلل الشركة النشطة)
- **المسار:** `app/Services/CurrentCompanyResolver.php`
- **الوظيفة:** خدمة مسؤولة عن استنتاج (Resolve) معرف الشركة النشطة.
- **آلية العمل:**
  1. تفحص الـ Request. إذا كان يحتوي على الهيدر `X-HWNIX-COMPANY`، تأخذ قيمته مباشرة (Mobile Context).
  2. إذا لم يكن الهيدر موجوداً، تعود للاعتماد على الحالة الكلاسيكية للويب وهي `$user->active_company_id`.

### 2. `MobileCompanyContextMiddleware` (طبقة الحماية للـ Context)
- **الممسار:** `app/Http/Middleware/MobileCompanyContextMiddleware.php`
- **الوظيفة:** اعتراض الطلبات القادمة لـ API للتأكد من أمان سياق الشركة.
- **آلية العمل:**
  - يقرأ الهيدر `X-HWNIX-COMPANY`.
  - إذا كان موجوداً، يتحقق من صلاحية المستخدم للوصول لهذه الشركة المحددة (سواء كان Super Admin، أو من خلال جدول `company_user`).
  - في حال عدم وجود صلاحية، يرد بـ `403 Forbidden`.
- **التسجيل:** تمت إضافته إلى مجموعة الـ `api` في `bootstrap/app.php` ليعمل تلقائياً مع جميع مسارات الـ API.

### 3. تعديل `CompanyScope` والـ `User Model`
- تم استبدال استدعاءات `Auth::user()->active_company_id` بـ `app(CurrentCompanyResolver::class)->resolve()`.
- **التأثير:** 
  - السكوب `company_filter` أصبح يتعرف على الـ Company Context ديناميكياً.
  - جميع الدوال في `User` مثل `getCashBoxesForCompany` و `getBalanceAttribute` أصبحت تستخدم السياق الموحد.

---

## 🔄 دورة حياة الطلب (Request Lifecycle)

1. يقوم تطبيق الموبايل بإرسال طلب (Request) لـ API.
2. يرفق التطبيق الـ Header: `X-HWNIX-COMPANY: 15`.
3. يمر الطلب عبر `MobileCompanyContextMiddleware`.
4. الميدل وير يتأكد أن المستخدم (Authenticated) لديه وصول للشركة (15).
5. يدخل الطلب للـ Controller والـ Services.
6. عند قيام الـ Model بعمليات استعلام، يتدخل الـ `CompanyScope`.
7. الـ `CompanyScope` يستدعي `CurrentCompanyResolver::resolve()`.
8. المحلل يرى الهيدر `X-HWNIX-COMPANY` فيقوم بإرجاع `15`.
9. يتم بناء الاستعلام مفلتراً بالشركة 15 `WHERE company_id = 15`.

---

## 🎯 المشاكل التي تم حلها
1. **Multi-device Race Conditions:** يمكن للمستخدم العمل على شركة (A) من الويب، وشركة (B) من الموبايل في نفس اللحظة دون تداخل في العمليات المالية أو الاستعلامات.
2. **Stateless API:** الـ APIs أصبحت تعتمد كلياً على حالة الـ Request (Stateless) بدلاً من الاعتماد على Session-like states في الـ Database كحقل `active_company_id`.
3. **التوسعة الآمنة:** جميع واجهات الموبايل وموديول الـ `HwnixCash` وأي تطبيق خارجي يمكنه الآن تمرير سياق الشركة بأمان تام، مع ضمان فلترة البيانات.

---
*تم تصميم وتنفيذ هذه المعمارية وفقاً لمعايير وإرشادات (GEMINI.md).*
