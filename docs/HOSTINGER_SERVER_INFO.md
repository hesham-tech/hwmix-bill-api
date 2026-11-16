# بيانات خادم Hostinger (بيئة الاختبار)

هذا الملف يحتوي على تفاصيل الاتصال بخادم Hostinger لبيئة الاختبار، وهو مرجع للفريق التقني.

## 🔑 تفاصيل الاتصال (SSH)

- **اسم المستخدم (Username):** `u715355537`
- **المنفذ (Port):** `65002`
- **عنوان الأي بي (IP):** `212.107.17.234`
- **أمر الاتصال:**
    ```bash
    ssh -p 65002 u715355537@212.107.17.234
    ```

### 🗝️ مفتاح SSH (Private Key)

```text
[REMOVED: use a dedicated GitHub Environment secret; never embed private keys]
```

---

## 🌐 الدومينات والمسارات

### 🖥️ واجهة المستخدم (Frontend)

- **الدومين:** `bill-teste.hwnix.com`
- **المسار:** `/home/u715355537/domains/hwnix.com/public_html/bill-teste`

### ⚙️ الواجهة الخلفية (API/Backend)

- **الدومين:** `api-teste.hwnix.com`
- **المسار:** `/home/u715355537/domains/hwnix.com/public_html/api-teste`
- **سجل الأخطاء (Frontend Errors):** `error.log` (موجود في الجذر) - يسجل أخطاء المتصفح والـ UI تلقائياً.

---

## 🗄️ بيانات قاعدة البيانات (Database)

- **اسم المستخدم:** `u715355537_api_teste`
- **اسم قاعدة البيانات:** `u715355537_api_teste`
- بيانات اعتماد قاعدة البيانات تُدار خارج Git داخل إعدادات الخادم الخاصة.
