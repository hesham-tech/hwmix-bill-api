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
-----BEGIN OPENSSH PRIVATE KEY-----
[ضع_المفتاح_هنا_بدون_مسافات_زائدة]
-----END OPENSSH PRIVATE KEY-----
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

استبدل القيم بما يناسب سيرفرك:

```powershell
ssh -i server_key.pem -p 65002 -o StrictHostKeyChecking=no u715355537@212.107.17.234
```
