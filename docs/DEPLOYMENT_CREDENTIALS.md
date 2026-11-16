# بيانات اعتماد النشر

لا تحفظ المفاتيح الخاصة أو كلمات المرور في ملفات المشروع أو التذاكر أو مخرجات CI. بيانات الاتصال العامة لا تمنح صلاحية الدخول وحدها.

| البيئة | فرع الباك إند | فرع الفرونت | GitHub Environment | Secret prefix |
|---|---|---|---|---|
| الإنتاج | master | main | production | PRODUCTION |
| الاختبار الحالي | staging | staging | staging | STAGING |
| الاختبار القديم | restructure-v2 | restructure-v2 | legacy-testing | LEGACY |

لكل مستودع ولكل بيئة مفتاح مستقل. PRIVATE_KEY وKNOWN_HOSTS محفوظان في Secrets بأسماء PREFIX_SSH_PRIVATE_KEY وPREFIX_SSH_KNOWN_HOSTS، وبيانات HOST/USER/PORT في Variables. تتطلب الـworkflows تثبيت هوية المضيف وStrictHostKeyChecking=yes دون fallback غير موثوق. يستعمل workflow التحقق أمر قراءة فقط، ولا ينشر كودًا أو يشغل artisan أو يغير قاعدة بيانات.

الفروع المسموحة في Environment مقيدة بفرع البيئة. يُسمح مؤقتًا بفرع المعالجة لاختبار المفاتيح من runner ثم يُزال السماح بعد التحقق. لا تُتاح الأسرار لفحص pull_request العام.

المفاتيح الجديدة على الخادم تستعمل restrict لمنع PTY وforwarding وuser rc. الاستضافة تشترك في مستخدم Unix بين المواقع؛ هذا ليس حصرًا لصلاحيات الملفات أو عزلاً بين المواقع، فالكود المنشور يعمل بصلاحيات الحساب. العزل النظامي يحتاج حسابًا أو استضافة مستقلة.

الدخول الإداري يستخدم مفتاحًا مستقلًا محفوظًا فقط في مجلد SSH الخاص بالجهاز بصلاحيات المالك. لكل مسؤول مفتاحه الخاص؛ لا يستخرج مفتاح CI من GitHub ولا يشارك المفتاح الإداري في المستودع. قبل إلغاء مفتاح قديم تحقق من دخول إداري بديل ومن نجاح مفاتيح النشر الجديدة. لا يُعاد تفعيل المفتاح المكشوف عند التراجع؛ أنشئ بديلًا جديدًا.

## منع التكرار

python3 tools/security/scan_tracked_secrets.py يفحص الملفات المتتبعة دون إظهار القيم. --staged يفحص نسخة index فعلًا، لا نسخة العمل التي يمكن تعديلها بعد git add. توجد اختبارات لحدود قراءة الملفات والمفاتيح المصطنعة وSecrets references. يشغل CI الفحص قبل النشر، ويوجد workflow عام للفحص. هذا فحص لأنماط المفاتيح الخاصة وبعض رموز الخدمات المعروفة؛ لا يثبت غياب كل أنواع الأسرار ولا يفحص ملفات المستخدم غير المتتبعة أو كامل التاريخ في كل push.

يُفحص التاريخ والسجلات والنسخ بصورة منفصلة. بقاء نسخة تاريخية من مفتاح ملغى لا يعيد صلاحيته؛ لا تنظف Git بالتاريخ المعاد أو تحذف أدلة ونسخًا قديمة دون تنسيق منفصل مع أصحاب الفروع والنسخ. لا تستعد ملفات نشر/وثائق قديمة تعيد المفتاح الملغى من backup.

تحديث workflow فقط على restructure-v2 يستخدم commit موسومًا لتجنب تشغيل التطبيق القديم أو migrations دون حاجة. تبقى push اللاحقة العادية على ذلك الفرع تعمل بالمفتاح الجديد. هذا لا يغير سياسة النشر التلقائي لفرع staging.

المراجع: [GitHub Environment Secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets)، [خيارات OpenSSH authorized_keys](https://man.openbsd.org/sshd.8#AUTHORIZED_KEYS_FILE_FORMAT)، [تجاوز تشغيل workflow لالتزام إداري](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/skip-workflow-runs).
