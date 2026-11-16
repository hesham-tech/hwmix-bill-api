---
description: دليل الاتصال بالسيرفر عبر SSH من بيئة ويندوز (Windows/PowerShell)
---

# متطلبات البيانات (Required Data)

عند طلب الاتصال بسيرفر جديد، يرجى تزويدي بالبيانات التالية:

1. **عنوان السيرفر (Host IP):** (مثال: `212.107.17.234`)
2. **اسم المستخدم (Username):** (مثال: `u715355537`)
3. **منفذ الاتصال (Port):** (مثال: `65002` أو `22`)
4. **المفتاح الخاص (Private Key):** المحتوى الكامل للمفتاح.

---

# خطوات الاتصال (Connection Workflow)

اتبع الخطوات التالية في PowerShell لإنشاء الملف وضبط صلاحياته ثم الاتصال.

## 1. إنشاء ملف المفتاح (Create Key File)

هذا السكربت يضمن استخدام Unix Line Endings (LF) الضرورية لعمل OpenSSH.

```powershell
$key = @"
[REMOVED: use a dedicated GitHub Environment secret; never embed private keys]
"@

# إضافة سطر جديد في النهاية إذا لم يوجد
if (-not $key.EndsWith("`n")) { $key += "`n" }

# تحويل الرموز إلى LF فقط
$cleanKey = $key -replace "`r`n", "`n"

# حفظ الملف
[System.IO.File]::WriteAllText("$PWD\server_key.pem", $cleanKey)
```

## 2. ضبط الصلاحيات (Fix Permissions)

هذه الخطوة حرجة؛ إذا لم تنفذها سيرفض SSH الملف بخطأ "Permissions are too open".

```powershell
# 1. إزالة جميع التوريثات (Inheritance)
# 2. منح صلاحية القراءة (Read) للمستخدم الحالي فقط
icacls server_key.pem /inheritance:r /grant:r "$($env:USERNAME):R"
```

## 3. الاتصال (Connect)

استخدم هذا الأمر للاتصال مباشرة:

```powershell
ssh -i server_key.pem -p 65002 -o StrictHostKeyChecking=no u715355537@212.107.17.234
```

---

# أوامر سريعة للتصحيح (Debugging Shortcuts)

### 📂 المسارات الأساسية (Core Paths)

- **API (Production):** `/home/u715355537/domains/hwnix.com/public_html/api`
- **Frontend (Production):** `/home/u715355537/domains/hwnix.com/public_html/bill`

### 📝 سجلات الأخطاء (Logs)

لقراءة آخر 50 خطأ في مشروع الـ API:

```bash
tail -n 50 /home/u715355537/domains/hwnix.com/public_html/api/storage/logs/laravel.log
```

لقراءة الأخطاء المسجلة من الواجهة (Frontend Errors):

```bash
tail -n 50 /home/u715355537/domains/hwnix.com/public_html/api/error.log
```

### 🗄️ الاستعلام عن جدول الأخطاء (Database Logs)

إذا فشل `tinker` بسبب تصاريح المجلد، استخدم SQL مباشرة:

```bash
mysql -u u715355537_api_teste -p u715355537_api_teste -e "SELECT id, message, type, created_at FROM error_reports ORDER BY id DESC LIMIT 5;"
```

_(ملاحظة: سيطلب كلمة المرور: `29Qjbd$J`)_
