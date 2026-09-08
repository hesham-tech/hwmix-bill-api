<?php

/**
 * -----------------------------------------------------------------------------
 * Permission Keys Registry �?? Arabic Labels
 * -----------------------------------------------------------------------------
 * �?ذا ا�?�?�?ف �?�? ا�?�?صدر ا�?�?ح�?د ا�?رس�?�? �?تعر�?ف �?فات�?ح ا�?ص�?اح�?ات (permission keys)
 * ا�?�?ستخد�?ة ف�? ا�?با�? إ�?د �?ا�?فر�?�?ت إ�?د�? �?�?ُرج�? ا�?رج�?ع إ�?�?�? ف�?ط �?�?حص�?�? ع�?�? أس�?اء
 * ا�?ص�?اح�?ات س�?اء ف�? ا�?�?�?د أ�? ع�?د إ�?شاء ب�?ا�?ات seeder أ�? ا�?تعا�?�? �?ع�?ا �?�? ا�?�?اج�?ة.
 *
 * �?? �?ُستخد�? �?ذا ا�?�?�?ف ف�?:
 * - ت�?�?�?د seeders ا�?خاصة با�?ص�?اح�?ات.
 * - إ�?شاء �?اج�?ات ا�?�?ستخد�? �?�?�?حة ا�?تح�?�?.
 * - ا�?تح�?�? �?�? ا�?ص�?اح�?ات ف�? Controllers, Policies, Gates إ�?خ.
 * - ا�?ترج�?ة �?ا�?ت�?ث�?�? ا�?بصر�? �?أس�?اء ا�?ص�?اح�?ات.
 *
 * �?? دا�?ة ا�?�?ساعد `perm_key('entity.action')` تُستخد�? �?�?�?ص�?�? إ�?�? ا�?�?فتاح ا�?رس�?�?.
 * �?� �?ثا�?: perm_key('users.update_all') �?? "users.update_all"
 *
 * �?? �?جب أ�? تحت�?�? �?�? ص�?اح�?ة ع�?�?:
 * - key   �?? ا�?اس�? ا�?�?�?حد ا�?�?حف�?ظ ف�? �?اعدة ا�?ب�?ا�?ات (با�?إ�?ج�?�?ز�?ة)
 * - label �?? ا�?تس�?�?ة ا�?ظا�?رة ف�? ا�?�?اج�?ة (با�?عرب�?ة)
 *
 * -----------------------------------------------------------------------------
 * شرح �?فص�? �?أ�?�?اع ا�?ص�?اح�?ات (actions) �?�?طا�?�?ا:
 * -----------------------------------------------------------------------------
 * - name: �?ش�?ر إ�?�? اس�? ا�?�?ج�?�?عة ا�?�?�?�?ة �?�?ص�?اح�?ات �?�?عبر ع�? �?ظ�?فت�?ا أ�? �?صف�?ا.
 * - page:
 * ا�?س�?اح با�?�?ص�?�? إ�?�? ا�?صفحة ا�?رئ�?س�?ة أ�? �?ائ�?ة إدارة �?�?ا�? �?ع�?�? (�?ث�? 'صفحة ا�?�?ستخد�?�?�?'
 * أ�? 'صفحة ا�?شر�?ات'). �?ا ت�?�?ح ص�?اح�?ات عرض ا�?سج�?ات�? ب�? ف�?ط ا�?�?ص�?�? �?�?اج�?ة ا�?إدارة.
 *
 * - view_all:
 * عرض ج�?�?ع ا�?سج�?ات �?�? ا�?�?�?ا�? ا�?�?ع�?�? **ض�?�? �?طا�? ا�?شر�?ة ا�?�?شطة** �?�?�?ستخد�?.
 * �?ا �?�?�?ح ص�?اح�?ات تعد�?�? أ�? حذف�? �?�?ر�? ا�?سج�?ات بغض ا�?�?ظر ع�? �?ُ�?شئ�?ا.
 *
 * - view_children:
 * عرض ا�?سج�?ات ا�?ت�? �?ا�? ا�?�?ستخد�? ا�?حا�?�? بإ�?شائ�?ا�? أ�? ا�?ت�? أ�?شأ�?ا ا�?�?ستخد�?�?�?
 * ا�?ذ�?�? �?تبع�?�? �?�? ف�? ا�?�?�?�?�? ا�?ت�?ظ�?�?�? (ا�?تابع�?�? �?�? أ�? "ا�?أب�?اء"). �?ُستخد�? �?ذا
 * ف�? ا�?أ�?ظ�?ة ا�?�?ر�?�?ة �?ت�?�?�?د ا�?رؤ�?ة ض�?�? فر�?ع �?ع�?�?ة.
 *
 * - view_self:
 * عرض ا�?سج�? ا�?ذ�? �?خص ا�?�?ستخد�? �?فس�? ف�?ط�? �?ث�? حساب�? ا�?شخص�? أ�? تفاص�?�? شر�?ت�?
 * ا�?خاصة ب�?. �?ُستخد�? �?ذا �?تعد�?�? ا�?ب�?ا�?ات ا�?شخص�?ة د�?�? رؤ�?ة ب�?ا�?ات ا�?آخر�?�?.
 *
 * - create:
 * إ�?شاء سج�? جد�?د ف�? �?ذا ا�?�?�?ا�? **ض�?�? �?طا�? ا�?شر�?ة ا�?�?شطة**�? �?ث�? إضافة �?ستخد�?
 * جد�?د أ�? إ�?شاء شر�?ة جد�?دة.
 *
 * - update_all:
 * تعد�?�? أ�? سج�? داخ�? ا�?�?�?ا�? **ض�?�? �?طا�? ا�?شر�?ة ا�?�?شطة** �?�?�?ستخد�?�? د�?�? �?�?�?د ع�?�?
 * �?�? أ�?شأ ا�?سج�? أ�? �?�?�?�?ت�?.
 *
 * - update_children:
 * تعد�?�? ا�?سج�?ات ا�?ت�? �?ا�? ا�?�?ستخد�? ا�?حا�?�? بإ�?شائ�?ا�? أ�? ا�?ت�? أ�?شأ�?ا ا�?�?ستخد�?�?�?
 * ا�?تابع�?�? �?�? ف�? ا�?�?�?�?�? ا�?ت�?ظ�?�?�? (ا�?أب�?اء).
 *
 * - update_self:
 * تعد�?�? ا�?سج�? ا�?�?رتبط با�?�?ستخد�? �?باشرة ف�?ط (�?ث�? تعد�?�? �?�?ف�? ا�?شخص�? أ�? ب�?ا�?ات شر�?ت�?
 * ا�?خاصة ب�?).
 *
 * - delete_all:
 * حذف أ�? سج�? �?�? ا�?�?�?ا�? **ض�?�? �?طا�? ا�?شر�?ة ا�?�?شطة** �?�?�?ستخد�?�? بغض ا�?�?ظر ع�? ا�?�?�?�?�?ة.
 *
 * - delete_children:
 * حذف ا�?سج�?ات ا�?ت�? �?ا�? ا�?�?ستخد�? ا�?حا�?�? بإ�?شائ�?ا�? أ�? ا�?ت�? أ�?شأ�?ا ا�?�?ستخد�?�?�?
 * ا�?تابع�?�? �?�? ف�? ا�?�?�?�?�? ا�?ت�?ظ�?�?�? (ا�?أب�?اء).
 *
 * - delete_self:
 * حذف ا�?سج�? ا�?خاص با�?�?ستخد�? �?فس�? ف�?ط (ع�?�? سب�?�? ا�?�?ثا�?�? تعط�?�? حساب�? ا�?شخص�?).
 *
 * �?� ا�?�?�?ا�?ات (entities): �?ث�? users, companies, warehouses �?� إ�?خ.
 * �?� �?�? �?�?ا�? �?حت�?�? ع�?�? �?ج�?�?عة �?�? ا�?ص�?اح�?ات حسب �?�?ع ا�?تعا�?�? �?ع�?.
 * -----------------------------------------------------------------------------
 */
return [
    // => ADMIN
    'admin' => [
        'name' => ['key' => 'admin', 'label' => 'ص�?اح�?ات ا�?�?د�?ر�?�?'],
        'page' => ['key' => 'admin.page', 'label' => 'ا�?صفحة ا�?رئ�?س�?ة'],
        'super' => ['key' => 'admin.super', 'label' => ' ص�?اح�?ة ا�?�?د�?ر ا�?عا�?'],
        'company' => ['key' => 'admin.company', 'label' => 'ص�?اح�?ة ادارة ا�?شر�?ة'],
    ],
    // => COMPANIES
    'companies' => [
        'name' => ['key' => 'companies', 'label' => 'ص�?اح�?ات إدارة ا�?شر�?ات'],
        'change_active_company' => ['key' => 'companies.change_active_company', 'label' => 'تغ�?�?ر ا�?شر�?ة ا�?�?شطة'],
        'page' => ['key' => 'companies.page', 'label' => 'صفحة ا�?شر�?ات'],

        'view_all' => ['key' => 'companies.view_all', 'label' => 'عرض �?�? ا�?شر�?ات'],
        'view_children' => ['key' => 'companies.view_children', 'label' => 'عرض ا�?شر�?ات ا�?تابعة'],
        'view_self' => ['key' => 'companies.view_self', 'label' => 'عرض ا�?شر�?ة ا�?حا�?�?ة'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'companies.create', 'label' => 'إ�?شاء شر�?ة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'companies.update_all', 'label' => 'تعد�?�? أ�? شر�?ة'],
        'update_children' => ['key' => 'companies.update_children', 'label' => 'تعد�?�? ا�?شر�?ات ا�?تابعة'],
        'update_self' => ['key' => 'companies.update_self', 'label' => 'تعد�?�? ا�?شر�?ة ا�?حا�?�?ة'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'companies.delete_all', 'label' => 'حذف أ�? شر�?ة'],
        'delete_children' => ['key' => 'companies.delete_children', 'label' => 'حذف ا�?شر�?ات ا�?تابعة'],
        'delete_self' => ['key' => 'companies.delete_self', 'label' => 'حذف ا�?شر�?ة ا�?حا�?�?ة'],
    ],

    // => BRANCHES
    'branches' => [
        'name' => ['key' => 'branches', 'label' => 'ص�?اح�?ات إدارة ا�?فر�?ع'],
        'page' => ['key' => 'branches.page', 'label' => 'صفحة ا�?فر�?ع'],

        'view_all' => ['key' => 'branches.view_all', 'label' => 'عرض �?�? ا�?فر�?ع'],
        'view_children' => ['key' => 'branches.view_children', 'label' => 'عرض ا�?فر�?ع ا�?تابعة'],
        'view_self' => ['key' => 'branches.view_self', 'label' => 'عرض ا�?فرع ا�?�?شط'],
        
        'create' => ['key' => 'branches.create', 'label' => 'إ�?شاء فرع'],
        
        'update_all' => ['key' => 'branches.update_all', 'label' => 'تعد�?�? أ�? فرع'],
        'update_children' => ['key' => 'branches.update_children', 'label' => 'تعد�?�? ا�?فر�?ع ا�?تابعة'],
        'update_self' => ['key' => 'branches.update_self', 'label' => 'تعد�?�? ا�?فرع ا�?�?شط'],
        
        'delete_all' => ['key' => 'branches.delete_all', 'label' => 'حذف أ�? فرع'],
        'delete_children' => ['key' => 'branches.delete_children', 'label' => 'حذف ا�?فر�?ع ا�?تابعة'],
        'delete_self' => ['key' => 'branches.delete_self', 'label' => 'حذف ا�?فرع ا�?�?شط'],
    ],
    // => USERS
    'users' => [
        'name' => ['key' => 'users', 'label' => 'ص�?اح�?ات إدارة ا�?�?ستخد�?�?�?'],
        'page' => ['key' => 'users.page', 'label' => 'صفحة ا�?�?ستخد�?�?�?'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'users.view_all', 'label' => 'عرض �?�? ا�?�?ستخد�?�?�?'],
        'view_children' => ['key' => 'users.view_children', 'label' => 'عرض ا�?�?ستخد�?�?�? ا�?تابع�?�?'],
        'view_self' => ['key' => 'users.view_self', 'label' => 'عرض ا�?حساب ا�?شخص�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'users.create', 'label' => 'إ�?شاء �?ستخد�?'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'users.update_all', 'label' => 'تعد�?�? أ�? �?ستخد�?'],
        'update_children' => ['key' => 'users.update_children', 'label' => 'تعد�?�? ا�?تابع�?�?'],
        'update_self' => ['key' => 'users.update_self', 'label' => 'تعد�?�? حساب�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'users.delete_all', 'label' => 'حذف أ�? �?ستخد�?'],
        'delete_children' => ['key' => 'users.delete_children', 'label' => 'حذف ا�?تابع�?�?'],
        'delete_self' => ['key' => 'users.delete_self', 'label' => 'حذف حساب�?'],
    ],
    // => PERSONAL ACCESS TOKENS
    'personal_access_tokens' => [
        'name' => ['key' => 'personal_access_tokens', 'label' => 'ص�?اح�?ات إدارة ر�?�?ز ا�?�?ص�?�? ا�?شخص�?ة'],
        'page' => ['key' => 'personal_access_tokens.page', 'label' => 'صفحة ر�?�?ز ا�?�?ص�?�? ا�?شخص�?ة'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'personal_access_tokens.view_all', 'label' => 'عرض �?�? ر�?�?ز ا�?�?ص�?�?'],
        'view_children' => ['key' => 'personal_access_tokens.view_children', 'label' => 'عرض ر�?�?ز ا�?�?ص�?�? ا�?ت�? أ�?شأ�?ا ا�?�?ستخد�?�?�? ا�?تابع�?�?'],
        'view_self' => ['key' => 'personal_access_tokens.view_self', 'label' => 'عرض ر�?�?ز ا�?�?ص�?�? ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'personal_access_tokens.create', 'label' => 'إ�?شاء ر�?ز �?ص�?�?'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'personal_access_tokens.update_all', 'label' => 'تعد�?�? أ�? ر�?ز �?ص�?�?'],
        'update_children' => ['key' => 'personal_access_tokens.update_children', 'label' => 'تعد�?�? ر�?�?ز ا�?�?ص�?�? ا�?ت�? أ�?شأ�?ا ا�?�?ستخد�?�?�? ا�?تابع�?�?'],
        'update_self' => ['key' => 'personal_access_tokens.update_self', 'label' => 'تعد�?�? ر�?�?ز ا�?�?ص�?�? ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'personal_access_tokens.delete_all', 'label' => 'حذف أ�? ر�?ز �?ص�?�?'],
        'delete_children' => ['key' => 'personal_access_tokens.delete_children', 'label' => 'حذف ر�?�?ز ا�?�?ص�?�? ا�?ت�? أ�?شأ�?ا ا�?�?ستخد�?�?�? ا�?تابع�?�?'],
        'delete_self' => ['key' => 'personal_access_tokens.delete_self', 'label' => 'حذف ر�?�?ز ا�?�?ص�?�? ا�?خاصة با�?�?ستخد�?'],
    ],
    // => TRANSLATIONS
    'translations' => [
        'name' => ['key' => 'translations', 'label' => 'ص�?اح�?ات إدارة ا�?ترج�?ات'],
        'page' => ['key' => 'translations.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?ترج�?ات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'translations.view_all', 'label' => 'عرض ج�?�?ع ا�?ترج�?ات'],
        'view_children' => ['key' => 'translations.view_children', 'label' => 'عرض ا�?ترج�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'translations.view_self', 'label' => 'عرض ا�?ترج�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'translations.create', 'label' => 'إ�?شاء ترج�?ة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'translations.update_all', 'label' => 'تعد�?�? أ�? ترج�?ة'],
        'update_children' => ['key' => 'translations.update_children', 'label' => 'تعد�?�? ا�?ترج�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'translations.update_self', 'label' => 'تعد�?�? ا�?ترج�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'translations.delete_all', 'label' => 'حذف أ�? ترج�?ة'],
        'delete_children' => ['key' => 'translations.delete_children', 'label' => 'حذف ا�?ترج�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'translations.delete_self', 'label' => 'حذف ا�?ترج�?ات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => TRANSACTIONS
    'transactions' => [
        'name' => ['key' => 'transactions', 'label' => 'ص�?اح�?ات إدارة ا�?�?عا�?�?ات'],
        'page' => ['key' => 'transactions.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?�?عا�?�?ات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'transactions.view_all', 'label' => 'عرض ج�?�?ع ا�?�?عا�?�?ات'],
        'view_children' => ['key' => 'transactions.view_children', 'label' => 'عرض ا�?�?عا�?�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'transactions.view_self', 'label' => 'عرض ا�?�?عا�?�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'transactions.create', 'label' => 'إ�?شاء �?عا�?�?ة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'transactions.update_all', 'label' => 'تعد�?�? أ�? �?عا�?�?ة'],
        'update_children' => ['key' => 'transactions.update_children', 'label' => 'تعد�?�? ا�?�?عا�?�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'transactions.update_self', 'label' => 'تعد�?�? ا�?�?عا�?�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'transactions.delete_all', 'label' => 'حذف أ�? �?عا�?�?ة'],
        'delete_children' => ['key' => 'transactions.delete_children', 'label' => 'حذف ا�?�?عا�?�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'transactions.delete_self', 'label' => 'حذف ا�?�?عا�?�?ات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => ACTIVITY LOGS
    'activity_logs' => [
        'name' => ['key' => 'activity_logs', 'label' => 'ص�?اح�?ات إدارة سج�?ات ا�?أ�?شطة'],
        'page' => ['key' => 'activity_logs.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة سج�?ات ا�?أ�?شطة'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'activity_logs.view_all', 'label' => 'عرض ج�?�?ع سج�?ات ا�?أ�?شطة'],
        'view_children' => ['key' => 'activity_logs.view_children', 'label' => 'عرض سج�?ات ا�?أ�?شطة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'activity_logs.view_self', 'label' => 'عرض سج�?ات ا�?أ�?شطة ا�?خاصة با�?�?ستخد�?'],

        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'activity_logs.delete_all', 'label' => 'حذف أ�? سج�? �?شاط'],
        'delete_children' => ['key' => 'activity_logs.delete_children', 'label' => 'حذف سج�?ات ا�?أ�?شطة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'activity_logs.delete_self', 'label' => 'حذف سج�?ات ا�?أ�?شطة ا�?خاصة با�?�?ستخد�?'],
    ],
    // => CASH BOX TYPES
    'cash_box_types' => [
        'name' => ['key' => 'cash_box_types', 'label' => 'ص�?اح�?ات إدارة أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة'],
        'page' => ['key' => 'cash_box_types.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'cash_box_types.view_all', 'label' => 'عرض ج�?�?ع أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة'],
        'view_children' => ['key' => 'cash_box_types.view_children', 'label' => 'عرض أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'cash_box_types.view_self', 'label' => 'عرض أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'cash_box_types.create', 'label' => 'إ�?شاء �?�?ع ص�?د�?�? �?�?د�?ة جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'cash_box_types.update_all', 'label' => 'تعد�?�? أ�? �?�?ع ص�?د�?�? �?�?د�?ة'],
        'update_children' => ['key' => 'cash_box_types.update_children', 'label' => 'تعد�?�? أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'cash_box_types.update_self', 'label' => 'تعد�?�? أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'cash_box_types.delete_all', 'label' => 'حذف أ�? �?�?ع ص�?د�?�? �?�?د�?ة'],
        'delete_children' => ['key' => 'cash_box_types.delete_children', 'label' => 'حذف أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'cash_box_types.delete_self', 'label' => 'حذف أ�?�?اع ص�?اد�?�? ا�?�?�?د�?ة ا�?خاصة با�?�?ستخد�?'],
    ],
    // => CASH BOXES
    'cash_boxes' => [
        'name' => ['key' => 'cash_boxes', 'label' => 'ص�?اح�?ات إدارة ص�?اد�?�? ا�?�?�?د�?ة'],
        'page' => ['key' => 'cash_boxes.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ص�?اد�?�? ا�?�?�?د�?ة'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'cash_boxes.view_all', 'label' => 'عرض ج�?�?ع ص�?اد�?�? ا�?�?�?د�?ة'],
        'view_children' => ['key' => 'cash_boxes.view_children', 'label' => 'عرض ص�?اد�?�? ا�?�?�?د�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'cash_boxes.view_self', 'label' => 'عرض ص�?اد�?�? ا�?�?�?د�?ة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'cash_boxes.create', 'label' => 'إ�?شاء ص�?د�?�? �?�?د�?ة جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'cash_boxes.update_all', 'label' => 'تعد�?�? أ�? ص�?د�?�? �?�?د�?ة'],
        'update_children' => ['key' => 'cash_boxes.update_children', 'label' => 'تعد�?�? ص�?اد�?�? ا�?�?�?د�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'cash_boxes.update_self', 'label' => 'تعد�?�? ص�?اد�?�? ا�?�?�?د�?ة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'cash_boxes.delete_all', 'label' => 'حذف أ�? ص�?د�?�? �?�?د�?ة'],
        'delete_children' => ['key' => 'cash_boxes.delete_children', 'label' => 'حذف ص�?اد�?�? ا�?�?�?د�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'cash_boxes.delete_self', 'label' => 'حذف ص�?اد�?�? ا�?�?�?د�?ة ا�?خاصة با�?�?ستخد�?'],
    ],
    // => IMAGES
    'images' => [
        'name' => ['key' => 'images', 'label' => 'ص�?اح�?ات إدارة ا�?ص�?ر'],
        'page' => ['key' => 'images.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?ص�?ر'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'images.view_all', 'label' => 'عرض ج�?�?ع ا�?ص�?ر'],
        'view_children' => ['key' => 'images.view_children', 'label' => 'عرض ا�?ص�?ر ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'images.view_self', 'label' => 'عرض ا�?ص�?ر ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'images.create', 'label' => 'إضافة ص�?رة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'images.update_all', 'label' => 'تعد�?�? أ�? ص�?رة'],
        'update_children' => ['key' => 'images.update_children', 'label' => 'تعد�?�? ا�?ص�?ر ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'images.update_self', 'label' => 'تعد�?�? ا�?ص�?ر ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'images.delete_all', 'label' => 'حذف أ�? ص�?رة'],
        'delete_children' => ['key' => 'images.delete_children', 'label' => 'حذف ا�?ص�?ر ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'images.delete_self', 'label' => 'حذف ا�?ص�?ر ا�?خاصة با�?�?ستخد�?'],
    ],
    // => WAREHOUSES
    'warehouses' => [
        'name' => ['key' => 'warehouses', 'label' => 'ص�?اح�?ات إدارة ا�?�?ست�?دعات'],
        'page' => ['key' => 'warehouses.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?�?ست�?دعات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'warehouses.view_all', 'label' => 'عرض ج�?�?ع ا�?�?ست�?دعات'],
        'view_children' => ['key' => 'warehouses.view_children', 'label' => 'عرض ا�?�?ست�?دعات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'warehouses.view_self', 'label' => 'عرض ا�?�?ست�?دعات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'warehouses.create', 'label' => 'إ�?شاء �?ست�?دع جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'warehouses.update_all', 'label' => 'تعد�?�? أ�? �?ست�?دع'],
        'update_children' => ['key' => 'warehouses.update_children', 'label' => 'تعد�?�? ا�?�?ست�?دعات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'warehouses.update_self', 'label' => 'تعد�?�? ا�?�?ست�?دعات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'warehouses.delete_all', 'label' => 'حذف أ�? �?ست�?دع'],
        'delete_children' => ['key' => 'warehouses.delete_children', 'label' => 'حذف ا�?�?ست�?دعات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'warehouses.delete_self', 'label' => 'حذف ا�?�?ست�?دعات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => CATEGORIES
    'categories' => [
        'name' => ['key' => 'categories', 'label' => 'ص�?اح�?ات إدارة ا�?فئات'],
        'page' => ['key' => 'categories.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?فئات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'categories.view_all', 'label' => 'عرض ج�?�?ع ا�?فئات'],
        'view_children' => ['key' => 'categories.view_children', 'label' => 'عرض ا�?فئات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'categories.view_self', 'label' => 'عرض ا�?فئات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'categories.create', 'label' => 'إ�?شاء فئة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'categories.update_all', 'label' => 'تعد�?�? أ�? فئة'],
        'update_children' => ['key' => 'categories.update_children', 'label' => 'تعد�?�? ا�?فئات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'categories.update_self', 'label' => 'تعد�?�? ا�?فئات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'categories.delete_all', 'label' => 'حذف أ�? فئة'],
        'delete_children' => ['key' => 'categories.delete_children', 'label' => 'حذف ا�?فئات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'categories.delete_self', 'label' => 'حذف ا�?فئات ا�?خاصة با�?�?ستخد�?'],
        'merge' => ['key' => 'categories.merge', 'label' => 'د�?ج ا�?فئات'],
        'globalize' => ['key' => 'categories.globalize', 'label' => 'تح�?�?�? ا�?فئة �?�?ظا�? عا�?�?�?'],
    ],
    // => BRANDS
    'brands' => [
        'name' => ['key' => 'brands', 'label' => 'ص�?اح�?ات إدارة ا�?ع�?ا�?ات ا�?تجار�?ة'],
        'page' => ['key' => 'brands.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?ع�?ا�?ات ا�?تجار�?ة'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'brands.view_all', 'label' => 'عرض ج�?�?ع ا�?ع�?ا�?ات ا�?تجار�?ة'],
        'view_children' => ['key' => 'brands.view_children', 'label' => 'عرض ا�?ع�?ا�?ات ا�?تجار�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'brands.view_self', 'label' => 'عرض ا�?ع�?ا�?ات ا�?تجار�?ة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'brands.create', 'label' => 'إ�?شاء ع�?ا�?ة تجار�?ة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'brands.update_all', 'label' => 'تعد�?�? أ�? ع�?ا�?ة تجار�?ة'],
        'update_children' => ['key' => 'brands.update_children', 'label' => 'تعد�?�? ا�?ع�?ا�?ات ا�?تجار�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'brands.update_self', 'label' => 'تعد�?�? ا�?ع�?ا�?ات ا�?تجار�?ة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'brands.delete_all', 'label' => 'حذف أ�? ع�?ا�?ة تجار�?ة'],
        'delete_children' => ['key' => 'brands.delete_children', 'label' => 'حذف ا�?ع�?ا�?ات ا�?تجار�?ة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'brands.delete_self', 'label' => 'حذف ا�?ع�?ا�?ات ا�?تجار�?ة ا�?خاصة با�?�?ستخد�?'],
        'merge' => ['key' => 'brands.merge', 'label' => 'د�?ج ا�?ع�?ا�?ات ا�?تجار�?ة'],
        'globalize' => ['key' => 'brands.globalize', 'label' => 'تح�?�?�? ا�?ع�?ا�?ة ا�?تجار�?ة �?�?ظا�? عا�?�?�?'],
    ],
    // => ATTRIBUTES
    'attributes' => [
        'name' => ['key' => 'attributes', 'label' => 'ص�?اح�?ات إدارة ا�?س�?ات'],
        'page' => ['key' => 'attributes.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?س�?ات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'attributes.view_all', 'label' => 'عرض ج�?�?ع ا�?س�?ات'],
        'view_children' => ['key' => 'attributes.view_children', 'label' => 'عرض ا�?س�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'attributes.view_self', 'label' => 'عرض ا�?س�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'attributes.create', 'label' => 'إ�?شاء س�?ة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'attributes.update_all', 'label' => 'تعد�?�? أ�? س�?ة'],
        'update_children' => ['key' => 'attributes.update_children', 'label' => 'تعد�?�? ا�?س�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'attributes.update_self', 'label' => 'تعد�?�? ا�?س�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'attributes.delete_all', 'label' => 'حذف أ�? س�?ة'],
        'delete_children' => ['key' => 'attributes.delete_children', 'label' => 'حذف ا�?س�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'attributes.delete_self', 'label' => 'حذف ا�?س�?ات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => ATTRIBUTE VALUES
    'attribute_values' => [
        'name' => ['key' => 'attribute_values', 'label' => 'ص�?اح�?ات إدارة �?�?�? ا�?س�?ات'],
        'page' => ['key' => 'attribute_values.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة �?�?�? ا�?س�?ات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'attribute_values.view_all', 'label' => 'عرض ج�?�?ع �?�?�? ا�?س�?ات'],
        'view_children' => ['key' => 'attribute_values.view_children', 'label' => 'عرض �?�?�? ا�?س�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'attribute_values.view_self', 'label' => 'عرض �?�?�? ا�?س�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'attribute_values.create', 'label' => 'إ�?شاء �?�?�?ة س�?ة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'attribute_values.update_all', 'label' => 'تعد�?�? أ�? �?�?�?ة س�?ة'],
        'update_children' => ['key' => 'attribute_values.update_children', 'label' => 'تعد�?�? �?�?�? ا�?س�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'attribute_values.update_self', 'label' => 'تعد�?�? �?�?�? ا�?س�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'attribute_values.delete_all', 'label' => 'حذف أ�? �?�?�?ة س�?ة'],
        'delete_children' => ['key' => 'attribute_values.delete_children', 'label' => 'حذف �?�?�? ا�?س�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'attribute_values.delete_self', 'label' => 'حذف �?�?�? ا�?س�?ات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => PRODUCTS
    'products' => [
        'name' => ['key' => 'products', 'label' => 'ص�?اح�?ات إدارة ا�?�?�?تجات'],
        'page' => ['key' => 'products.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?�?�?تجات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'products.view_all', 'label' => 'عرض ج�?�?ع ا�?�?�?تجات'],
        'view_children' => ['key' => 'products.view_children', 'label' => 'عرض ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'products.view_self', 'label' => 'عرض ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'products.create', 'label' => 'إ�?شاء �?�?تج جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'products.update_all', 'label' => 'تعد�?�? أ�? �?�?تج'],
        'update_children' => ['key' => 'products.update_children', 'label' => 'تعد�?�? ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'products.update_self', 'label' => 'تعد�?�? ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'products.delete_all', 'label' => 'حذف أ�? �?�?تج'],
        'delete_children' => ['key' => 'products.delete_children', 'label' => 'حذف ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'products.delete_self', 'label' => 'حذف ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
        'view_wholesale_price' => ['key' => 'products.view_wholesale_price', 'label' => 'عرض سعر ا�?ج�?�?ة'],
        'view_purchase_price' => ['key' => 'products.view_purchase_price', 'label' => 'عرض سعر ا�?شراء'],
        'print_labels' => ['key' => 'products.print_labels', 'label' => 'طباعة ا�?�?�?ص�?ات �?ا�?بار�?�?د'],
        'import' => ['key' => 'products.import', 'label' => 'است�?راد ا�?�?�?تجات'],
        'export' => ['key' => 'products.export', 'label' => 'تصد�?ر ا�?�?�?تجات'],
    ],
    // => PRODUCT VARIANTS
    'product_variants' => [
        'name' => ['key' => 'product_variants', 'label' => 'ص�?اح�?ات إدارة �?تغ�?رات ا�?�?�?تجات'],
        'page' => ['key' => 'product_variants.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة �?تغ�?رات ا�?�?�?تجات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'product_variants.view_all', 'label' => 'عرض ج�?�?ع �?تغ�?رات ا�?�?�?تجات'],
        'view_children' => ['key' => 'product_variants.view_children', 'label' => 'عرض �?تغ�?رات ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'product_variants.view_self', 'label' => 'عرض �?تغ�?رات ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'product_variants.create', 'label' => 'إ�?شاء �?تغ�?ر �?�?تج جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'product_variants.update_all', 'label' => 'تعد�?�? أ�? �?تغ�?ر �?�?تج'],
        'update_children' => ['key' => 'product_variants.update_children', 'label' => 'تعد�?�? �?تغ�?رات ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'product_variants.update_self', 'label' => 'تعد�?�? �?تغ�?رات ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'product_variants.delete_all', 'label' => 'حذف أ�? �?تغ�?ر �?�?تج'],
        'delete_children' => ['key' => 'product_variants.delete_children', 'label' => 'حذف �?تغ�?رات ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'product_variants.delete_self', 'label' => 'حذف �?تغ�?رات ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => PRODUCT VARIANT ATTRIBUTES
    'product_variant_attributes' => [
        'name' => ['key' => 'product_variant_attributes', 'label' => 'ص�?اح�?ات إدارة س�?ات �?تغ�?رات ا�?�?�?تجات'],
        'page' => ['key' => 'product_variant_attributes.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة س�?ات �?تغ�?رات ا�?�?�?تجات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'product_variant_attributes.view_all', 'label' => 'عرض ج�?�?ع س�?ات �?تغ�?رات ا�?�?�?تجات'],
        'view_children' => ['key' => 'product_variant_attributes.view_children', 'label' => 'عرض س�?ات �?تغ�?رات ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'product_variant_attributes.view_self', 'label' => 'عرض س�?ات �?تغ�?رات ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'product_variant_attributes.create', 'label' => 'إ�?شاء س�?ة �?تغ�?ر �?�?تج جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'product_variant_attributes.update_all', 'label' => 'تعد�?�? أ�? س�?ة �?تغ�?ر �?�?تج'],
        'update_children' => ['key' => 'product_variant_attributes.update_children', 'label' => 'تعد�?�? س�?ات �?تغ�?رات ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'product_variant_attributes.update_self', 'label' => 'تعد�?�? س�?ات �?تغ�?رات ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'product_variant_attributes.delete_all', 'label' => 'حذف أ�? س�?ة �?تغ�?ر �?�?تج'],
        'delete_children' => ['key' => 'product_variant_attributes.delete_children', 'label' => 'حذف س�?ات �?تغ�?رات ا�?�?�?تجات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'product_variant_attributes.delete_self', 'label' => 'حذف س�?ات �?تغ�?رات ا�?�?�?تجات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => STOCKS
    'stocks' => [
        'name' => ['key' => 'stocks', 'label' => 'ص�?اح�?ات إدارة ا�?�?خز�?�?'],
        'page' => ['key' => 'stocks.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?�?خز�?�?'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'stocks.view_all', 'label' => 'عرض ج�?�?ع سج�?ات ا�?�?خز�?�?'],
        'view_children' => ['key' => 'stocks.view_children', 'label' => 'عرض سج�?ات ا�?�?خز�?�? ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'stocks.view_self', 'label' => 'عرض سج�?ات ا�?�?خز�?�? ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'stocks.create', 'label' => 'إ�?شاء سج�? �?خز�?�? جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'stocks.update_all', 'label' => 'تعد�?�? أ�? سج�? �?خز�?�?'],
        'update_children' => ['key' => 'stocks.update_children', 'label' => 'تعد�?�? سج�?ات ا�?�?خز�?�? ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'stocks.update_self', 'label' => 'تعد�?�? سج�?ات ا�?�?خز�?�? ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'stocks.delete_all', 'label' => 'حذف أ�? سج�? �?خز�?�?'],
        'delete_children' => ['key' => 'stocks.delete_children', 'label' => 'حذف سج�?ات ا�?�?خز�?�? ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'stocks.delete_self', 'label' => 'حذف سج�?ات ا�?�?خز�?�? ا�?خاصة با�?�?ستخد�?'],
        'manual_adjustment' => ['key' => 'stocks.manual_adjustment', 'label' => 'ا�?تعد�?�? ا�?�?د�?�? �?�?�?خز�?�?'],
    ],
    // => INVOICES
    'invoices' => [
        'name' => ['key' => 'invoices', 'label' => '??????? ????? ????????'],
        'page' => ['key' => 'invoices.page', 'label' => '?????? ??? ???? ????????'],
        // ??????? ????? (View)
        'view_all' => ['key' => 'invoices.view_all', 'label' => '??? ???? ????????'],
        'view_children' => ['key' => 'invoices.view_children', 'label' => '??? ???????? ???? ?????? ????????'],
        'view_self' => ['key' => 'invoices.view_self', 'label' => '??? ???????? ?????? ?????????'],
        // ??????? ??????? (Create)
        'create' => ['key' => 'invoices.create', 'label' => '????? ?????? ?????'],
        // ??????? ??????? (Update)
        'update_all' => ['key' => 'invoices.update_all', 'label' => '????? ?? ??????'],
        'update_children' => ['key' => 'invoices.update_children', 'label' => '????? ???????? ???? ?????? ????????'],
        'update_self' => ['key' => 'invoices.update_self', 'label' => '????? ???????? ?????? ?????????'],
        // ??????? ????? (Delete)
        'delete_all' => ['key' => 'invoices.delete_all', 'label' => '??? ?? ??????'],
        'delete_children' => ['key' => 'invoices.delete_children', 'label' => '??? ???????? ???? ?????? ????????'],
        'delete_self' => ['key' => 'invoices.delete_self', 'label' => '??? ???????? ?????? ?????????'],
        'print' => ['key' => 'invoices.print', 'label' => '????? ????????'],
        // ??????? ???? ? Sensitive Financial Data
        'view_profit' => ['key' => 'invoices.view_profit', 'label' => '??? ???? ????? ?? ????????'],
    ],    // => INSTALLMENT PLANS
    'installment_plans' => [
        'name' => ['key' => 'installment_plans', 'label' => 'ص�?اح�?ات إدارة خطط ا�?أ�?ساط'],
        'page' => ['key' => 'installment_plans.page', 'label' => 'ا�?�?ص�?�? إ�?�? ص� حة خطط ا�?أ�?ساط'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'installment_plans.view_all', 'label' => 'عرض ج�?�?ع خطط ا�?أ�?ساط'],
        'view_children' => ['key' => 'installment_plans.view_children', 'label' => 'عرض خطط ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'installment_plans.view_self', 'label' => 'عرض خطط ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'installment_plans.create', 'label' => 'إ�?شاء خطة أ�?ساط جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'installment_plans.update_all', 'label' => 'تعد�?�? أ�? خطة أ�?ساط'],
        'update_children' => ['key' => 'installment_plans.update_children', 'label' => 'تعد�?�? خطط ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'installment_plans.update_self', 'label' => 'تعد�?�? خطط ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'installment_plans.delete_all', 'label' => 'حذف أ�? خطة أ�?ساط'],
        'delete_children' => ['key' => 'installment_plans.delete_children', 'label' => 'حذف خطط ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'installment_plans.delete_self', 'label' => 'حذف خطط ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
    ],
    // => INSTALLMENTS
    'installments' => [
        'name' => ['key' => 'installments', 'label' => 'ص�?اح�?ات إدارة ا�?أ�?ساط'],
        'page' => ['key' => 'installments.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?أ�?ساط'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'installments.view_all', 'label' => 'عرض ج�?�?ع ا�?أ�?ساط'],
        'view_children' => ['key' => 'installments.view_children', 'label' => 'عرض ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'installments.view_self', 'label' => 'عرض ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'installments.create', 'label' => 'إ�?شاء �?سط جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'installments.update_all', 'label' => 'تعد�?�? أ�? �?سط'],
        'update_children' => ['key' => 'installments.update_children', 'label' => 'تعد�?�? ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'installments.update_self', 'label' => 'تعد�?�? ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'installments.delete_all', 'label' => 'حذف أ�? �?سط'],
        'delete_children' => ['key' => 'installments.delete_children', 'label' => 'حذف ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'installments.delete_self', 'label' => 'حذف ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
    ],
    // => INSTALLMENT PAYMENTS
    'installment_payments' => [
        'name' => ['key' => 'installment_payments', 'label' => 'ص�?اح�?ات إدارة �?دف�?عات ا�?أ�?ساط'],
        'page' => ['key' => 'installment_payments.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة �?دف�?عات ا�?أ�?ساط'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'installment_payments.view_all', 'label' => 'عرض ج�?�?ع �?دف�?عات ا�?أ�?ساط'],
        'view_children' => ['key' => 'installment_payments.view_children', 'label' => 'عرض �?دف�?عات ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'installment_payments.view_self', 'label' => 'عرض �?دف�?عات ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'installment_payments.create', 'label' => 'إ�?شاء دفعة �?سط جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'installment_payments.update_all', 'label' => 'تعد�?�? أ�? دفعة �?سط'],
        'update_children' => ['key' => 'installment_payments.update_children', 'label' => 'تعد�?�? �?دف�?عات ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'installment_payments.update_self', 'label' => 'تعد�?�? �?دف�?عات ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'installment_payments.delete_all', 'label' => 'حذف أ�? دفعة �?سط'],
        'delete_children' => ['key' => 'installment_payments.delete_children', 'label' => 'حذف �?دف�?عات ا�?أ�?ساط ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'installment_payments.delete_self', 'label' => 'حذف �?دف�?عات ا�?أ�?ساط ا�?خاصة با�?�?ستخد�?'],
    ],
    // => INVOICE ITEMS
    'invoice_items' => [
        'name' => ['key' => 'invoice_items', 'label' => 'ص�?اح�?ات إدارة ع�?اصر ا�?فات�?رة'],
        'page' => ['key' => 'invoice_items.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ع�?اصر ا�?فات�?رة'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'invoice_items.view_all', 'label' => 'عرض ج�?�?ع ع�?اصر ا�?فات�?رة'],
        'view_children' => ['key' => 'invoice_items.view_children', 'label' => 'عرض ع�?اصر ا�?فات�?رة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'invoice_items.view_self', 'label' => 'عرض ع�?اصر ا�?فات�?رة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'invoice_items.create', 'label' => 'إ�?شاء ع�?صر فات�?رة جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'invoice_items.update_all', 'label' => 'تعد�?�? أ�? ع�?صر فات�?رة'],
        'update_children' => ['key' => 'invoice_items.update_children', 'label' => 'تعد�?�? ع�?اصر ا�?فات�?رة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'invoice_items.update_self', 'label' => 'تعد�?�? ع�?اصر ا�?فات�?رة ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'invoice_items.delete_all', 'label' => 'حذف أ�? ع�?صر فات�?رة'],
        'delete_children' => ['key' => 'invoice_items.delete_children', 'label' => 'حذف ع�?اصر ا�?فات�?رة ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'invoice_items.delete_self', 'label' => 'حذف ع�?اصر ا�?فات�?رة ا�?خاصة با�?�?ستخد�?'],
    ],
    // => PAYMENTS
    'payments' => [
        'name' => ['key' => 'payments', 'label' => 'ص�?اح�?ات إدارة ا�?�?دف�?عات'],
        'page' => ['key' => 'payments.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?�?دف�?عات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'payments.view_all', 'label' => 'عرض ج�?�?ع ا�?�?دف�?عات'],
        'view_children' => ['key' => 'payments.view_children', 'label' => 'عرض ا�?�?دف�?عات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'payments.view_self', 'label' => 'عرض ا�?�?دف�?عات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'payments.create', 'label' => 'إ�?شاء دفعة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'payments.update_all', 'label' => 'تعد�?�? أ�? دفعة'],
        'update_children' => ['key' => 'payments.update_children', 'label' => 'تعد�?�? ا�?�?دف�?عات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'payments.update_self', 'label' => 'تعد�?�? ا�?�?دف�?عات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'payments.delete_all', 'label' => 'حذف أ�? دفعة'],
        'delete_children' => ['key' => 'payments.delete_children', 'label' => 'حذف ا�?�?دف�?عات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'payments.delete_self', 'label' => 'حذف ا�?�?دف�?عات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => PAYMENT METHODS
    'payment_methods' => [
        'name' => ['key' => 'payment_methods', 'label' => 'ص�?اح�?ات إدارة طر�? ا�?دفع'],
        'page' => ['key' => 'payment_methods.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة طر�? ا�?دفع'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'payment_methods.view_all', 'label' => 'عرض ج�?�?ع طر�? ا�?دفع'],
        'view_children' => ['key' => 'payment_methods.view_children', 'label' => 'عرض طر�? ا�?دفع ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'payment_methods.view_self', 'label' => 'عرض طر�? ا�?دفع ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'payment_methods.create', 'label' => 'إ�?شاء طر�?�?ة دفع جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'payment_methods.update_all', 'label' => 'تعد�?�? أ�? طر�?�?ة دفع'],
        'update_children' => ['key' => 'payment_methods.update_children', 'label' => 'تعد�?�? طر�? ا�?دفع ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'payment_methods.update_self', 'label' => 'تعد�?�? طر�? ا�?دفع ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'payment_methods.delete_all', 'label' => 'حذف أ�? طر�?�?ة دفع'],
        'delete_children' => ['key' => 'payment_methods.delete_children', 'label' => 'حذف طر�? ا�?دفع ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'payment_methods.delete_self', 'label' => 'حذف طر�? ا�?دفع ا�?خاصة با�?�?ستخد�?'],
    ],
    // => REVENUES
    'revenues' => [
        'name' => ['key' => 'revenues', 'label' => 'ص�?اح�?ات إدارة ا�?إ�?رادات'],
        'page' => ['key' => 'revenues.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?إ�?رادات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'revenues.view_all', 'label' => 'عرض ج�?�?ع ا�?إ�?رادات'],
        'view_children' => ['key' => 'revenues.view_children', 'label' => 'عرض ا�?إ�?رادات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'revenues.view_self', 'label' => 'عرض ا�?إ�?رادات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'revenues.create', 'label' => 'إ�?شاء سج�? إ�?راد جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'revenues.update_all', 'label' => 'تعد�?�? أ�? إ�?راد'],
        'update_children' => ['key' => 'revenues.update_children', 'label' => 'تعد�?�? ا�?إ�?رادات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'revenues.update_self', 'label' => 'تعد�?�? ا�?إ�?رادات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'revenues.delete_all', 'label' => 'حذف أ�? إ�?راد'],
        'delete_children' => ['key' => 'revenues.delete_children', 'label' => 'حذف ا�?إ�?رادات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'revenues.delete_self', 'label' => 'حذف ا�?إ�?رادات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => PROFITS
    'profits' => [
        'name' => ['key' => 'profits', 'label' => 'ص�?اح�?ات إدارة ا�?أرباح'],
        'page' => ['key' => 'profits.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?أرباح'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'profits.view_all', 'label' => 'عرض ج�?�?ع ا�?أرباح'],
        'view_children' => ['key' => 'profits.view_children', 'label' => 'عرض ا�?أرباح ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'profits.view_self', 'label' => 'عرض ا�?أرباح ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'profits.create', 'label' => 'إ�?شاء سج�? ربح جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'profits.update_all', 'label' => 'تعد�?�? أ�? ربح'],
        'update_children' => ['key' => 'profits.update_children', 'label' => 'تعد�?�? ا�?أرباح ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'profits.update_self', 'label' => 'تعد�?�? ا�?أرباح ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'profits.delete_all', 'label' => 'حذف أ�? ربح'],
        'delete_children' => ['key' => 'profits.delete_children', 'label' => 'حذف ا�?أرباح ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'profits.delete_self', 'label' => 'حذف ا�?أرباح ا�?خاصة با�?�?ستخد�?'],
    ],
    // => SERVICES
    'services' => [
        'name' => ['key' => 'services', 'label' => 'ص�?اح�?ات إدارة ا�?خد�?ات'],
        'page' => ['key' => 'services.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?خد�?ات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'services.view_all', 'label' => 'عرض ج�?�?ع ا�?خد�?ات'],
        'view_children' => ['key' => 'services.view_children', 'label' => 'عرض ا�?خد�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'services.view_self', 'label' => 'عرض ا�?خد�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'services.create', 'label' => 'إ�?شاء خد�?ة جد�?دة'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'services.update_all', 'label' => 'تعد�?�? أ�? خد�?ة'],
        'update_children' => ['key' => 'services.update_children', 'label' => 'تعد�?�? ا�?خد�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'services.update_self', 'label' => 'تعد�?�? ا�?خد�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'services.delete_all', 'label' => 'حذف أ�? خد�?ة'],
        'delete_children' => ['key' => 'services.delete_children', 'label' => 'حذف ا�?خد�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'services.delete_self', 'label' => 'حذف ا�?خد�?ات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => SUBSCRIPTIONS
    'subscriptions' => [
        'name' => ['key' => 'subscriptions', 'label' => 'ص�?اح�?ات إدارة ا�?اشترا�?ات'],
        'page' => ['key' => 'subscriptions.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?اشترا�?ات'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'subscriptions.view_all', 'label' => 'عرض ج�?�?ع ا�?اشترا�?ات'],
        'view_children' => ['key' => 'subscriptions.view_children', 'label' => 'عرض ا�?اشترا�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'subscriptions.view_self', 'label' => 'عرض ا�?اشترا�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'subscriptions.create', 'label' => 'إ�?شاء اشترا�? جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'subscriptions.update_all', 'label' => 'تعد�?�? أ�? اشترا�?'],
        'update_children' => ['key' => 'subscriptions.update_children', 'label' => 'تعد�?�? ا�?اشترا�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'subscriptions.update_self', 'label' => 'تعد�?�? ا�?اشترا�?ات ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'subscriptions.delete_all', 'label' => 'حذف أ�? اشترا�?'],
        'delete_children' => ['key' => 'subscriptions.delete_children', 'label' => 'حذف ا�?اشترا�?ات ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'subscriptions.delete_self', 'label' => 'حذف ا�?اشترا�?ات ا�?خاصة با�?�?ستخد�?'],
    ],
    // => ROLES
    'roles' => [
        'name' => ['key' => 'roles', 'label' => 'ص�?اح�?ات إدارة ا�?أد�?ار'],
        'page' => ['key' => 'roles.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?أد�?ار'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'roles.view_all', 'label' => 'عرض ج�?�?ع ا�?أد�?ار'],
        'view_children' => ['key' => 'roles.view_children', 'label' => 'عرض ا�?أد�?ار ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'view_self' => ['key' => 'roles.view_self', 'label' => 'عرض ا�?أد�?ار ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'roles.create', 'label' => 'إ�?شاء د�?ر جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'roles.update_all', 'label' => 'تعد�?�? أ�? د�?ر'],
        'update_children' => ['key' => 'roles.update_children', 'label' => 'تعد�?�? ا�?أد�?ار ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'update_self' => ['key' => 'roles.update_self', 'label' => 'تعد�?�? ا�?أد�?ار ا�?خاصة با�?�?ستخد�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'roles.delete_all', 'label' => 'حذف أ�? د�?ر'],
        'delete_children' => ['key' => 'roles.delete_children', 'label' => 'حذف ا�?أد�?ار ا�?ت�? أ�?شأ�?ا ا�?تابع�?�?'],
        'delete_self' => ['key' => 'roles.delete_self', 'label' => 'حذف ا�?أد�?ار ا�?خاصة با�?�?ستخد�?'],
    ],
    // => EXPENSES
    'expenses' => [
        'name' => ['key' => 'expenses', 'label' => 'ص�?اح�?ات إدارة ا�?�?صار�?ف'],
        'page' => ['key' => 'expenses.page', 'label' => 'ا�?�?ص�?�? إ�?�? صفحة ا�?�?صار�?ف'],
        // ص�?اح�?ات ا�?عرض (View)
        'view_all' => ['key' => 'expenses.view_all', 'label' => 'عرض ج�?�?ع ا�?�?صار�?ف'],
        'view_children' => ['key' => 'expenses.view_children', 'label' => 'عرض �?صار�?ف ا�?تابع�?�?'],
        'view_self' => ['key' => 'expenses.view_self', 'label' => 'عرض ا�?�?صار�?ف ا�?شخص�?ة'],
        // ص�?اح�?ات ا�?إ�?شاء (Create)
        'create' => ['key' => 'expenses.create', 'label' => 'تسج�?�? �?صر�?ف جد�?د'],
        // ص�?اح�?ات ا�?تعد�?�? (Update)
        'update_all' => ['key' => 'expenses.update_all', 'label' => 'تعد�?�? أ�? �?صر�?ف'],
        'update_children' => ['key' => 'expenses.update_children', 'label' => 'تعد�?�? �?صار�?ف ا�?تابع�?�?'],
        'update_self' => ['key' => 'expenses.update_self', 'label' => 'تعد�?�? ا�?�?صر�?ف ا�?شخص�?'],
        // ص�?اح�?ات ا�?حذف (Delete)
        'delete_all' => ['key' => 'expenses.delete_all', 'label' => 'حذف أ�? �?صر�?ف'],
        'delete_children' => ['key' => 'expenses.delete_children', 'label' => 'حذف �?صار�?ف ا�?تابع�?�?'],
        'delete_self' => ['key' => 'expenses.delete_self', 'label' => 'حذف ا�?�?صر�?ف ا�?شخص�?'],
    ],
    // => EXPENSE CATEGORIES
    'expense_categories' => [
        'name' => ['key' => 'expense_categories', 'label' => 'ص�?اح�?ات إدارة تص�?�?فات ا�?�?صار�?ف'],
        'page' => ['key' => 'expense_categories.page', 'label' => 'ا�?�?ص�?�? �?صفحة تص�?�?فات ا�?�?صار�?ف'],
        'view_all' => ['key' => 'expense_categories.view_all', 'label' => 'عرض ج�?�?ع ا�?تص�?�?فات'],
        'create' => ['key' => 'expense_categories.create', 'label' => 'إضافة تص�?�?ف جد�?د'],
        'update_all' => ['key' => 'expense_categories.update_all', 'label' => 'تعد�?�? أ�? تص�?�?ف'],
        'delete_all' => ['key' => 'expense_categories.delete_all', 'label' => 'حذف أ�? تص�?�?ف'],
    ],
    // => FINANCIAL LEDGER
    'financial_ledger' => [
        'name' => ['key' => 'financial_ledger', 'label' => 'ص�?اح�?ات دفتر ا�?أستاذ ا�?عا�?'],
        'page' => ['key' => 'financial_ledger.page', 'label' => 'ا�?�?ص�?�? �?دفتر ا�?أستاذ'],
        'view_all' => ['key' => 'financial_ledger.view_all', 'label' => 'عرض ج�?�?ع ا�?�?�?�?د ا�?�?حاسب�?ة'],
        'view_self' => ['key' => 'financial_ledger.view_self', 'label' => 'عرض ا�?�?�?�?د ا�?خاصة با�?�?ستخد�?'],
        'export' => ['key' => 'financial_ledger.export', 'label' => 'تصد�?ر سج�?ات ا�?أستاذ'],
    ],
    // => REPORTS
    'reports' => [
        'name' => ['key' => 'reports', 'label' => 'ص�?اح�?ات ا�?ت�?ار�?ر'],
        'page' => ['key' => 'reports.page', 'label' => 'ا�?�?ص�?�? �?صفحة ا�?ت�?ار�?ر'],
        'view_all' => ['key' => 'reports.view_all', 'label' => 'عرض ج�?�?ع ا�?ت�?ار�?ر'],
        'sales' => ['key' => 'reports.sales', 'label' => 'عرض ت�?ر�?ر ا�?�?ب�?عات'],
        'stock' => ['key' => 'reports.stock', 'label' => 'عرض ت�?ر�?ر ا�?�?خز�?�?'],
        'profit' => ['key' => 'reports.profit', 'label' => 'عرض ت�?ر�?ر ا�?أرباح �?ا�?خسائر'],
        'expenses' => ['key' => 'reports.expenses', 'label' => 'عرض ت�?ر�?ر ا�?�?صر�?فات ا�?تفص�?�?�?'],
        'cash_flow' => ['key' => 'reports.cash_flow', 'label' => 'عرض ت�?ر�?ر ا�?تدف�? ا�?�?�?د�?'],
        'tax' => ['key' => 'reports.tax', 'label' => 'عرض ت�?ر�?ر ا�?ضرائب'],
        'export' => ['key' => 'reports.export', 'label' => 'تصد�?ر ا�?ت�?ار�?ر'],
    ],
    // => INVOICE TYPES
    'invoice_types' => [
        'name' => ['key' => 'invoice_types', 'label' => 'ص�?اح�?ات أ�?�?اع ا�?�?ست�?دات'],
        'page' => ['key' => 'invoice_types.page', 'label' => 'صفحة أ�?�?اع ا�?�?ست�?دات'],
        'view_all' => ['key' => 'invoice_types.view_all', 'label' => 'عرض �?�? أ�?�?اع ا�?�?ست�?دات'],
        'view_children' => ['key' => 'invoice_types.view_children', 'label' => 'عرض ا�?أ�?�?اع �?�?تابع�?�?'],
        'view_self' => ['key' => 'invoice_types.view_self', 'label' => 'عرض ا�?أ�?�?اع ا�?خاصة'],
        'update_all' => ['key' => 'invoice_types.update_all', 'label' => 'تعد�?�? أ�? �?�?ع (تفع�?�?/تعط�?�?)'],
    ],
    // => PLANS
    'plans' => [
        'name' => ['key' => 'plans', 'label' => 'ص�?اح�?ات خطط ا�?أسعار'],
        'page' => ['key' => 'plans.page', 'label' => 'صفحة خطط ا�?أسعار'],
        'view_all' => ['key' => 'plans.view_all', 'label' => 'عرض ج�?�?ع ا�?خطط'],
        'view_children' => ['key' => 'plans.view_children', 'label' => 'عرض خطط ا�?تابع�?�?'],
        'view_self' => ['key' => 'plans.view_self', 'label' => 'عرض خطط�? ا�?شخص�?ة'],
        'create' => ['key' => 'plans.create', 'label' => 'إ�?شاء خطة جد�?دة'],
        'update_all' => ['key' => 'plans.update_all', 'label' => 'تعد�?�? أ�? خطة'],
        'update_children' => ['key' => 'plans.update_children', 'label' => 'تعد�?�? خطط ا�?تابع�?�?'],
        'update_self' => ['key' => 'plans.update_self', 'label' => 'تعد�?�? خطت�? ا�?شخص�?ة'],
        'delete_all' => ['key' => 'plans.delete_all', 'label' => 'حذف أ�? خطة'],
        'delete_children' => ['key' => 'plans.delete_children', 'label' => 'حذف خطط ا�?تابع�?�?'],
        'delete_self' => ['key' => 'plans.delete_self', 'label' => 'حذف خطت�? ا�?شخص�?ة'],
    ],
    // => TASKS
    'tasks' => [
        'name' => ['key' => 'tasks', 'label' => 'ص�?اح�?ات إدارة ا�?�?�?ا�?'],
        'page' => ['key' => 'tasks.page', 'label' => 'صفحة ا�?�?�?ا�?'],
        'view_all' => ['key' => 'tasks.view_all', 'label' => 'عرض ج�?�?ع ا�?�?�?ا�?'],
        'view_children' => ['key' => 'tasks.view_children', 'label' => 'عرض �?�?ا�? ا�?تابع�?�?'],
        'view_self' => ['key' => 'tasks.view_self', 'label' => 'عرض �?�?ا�?�? ا�?شخص�?ة'],
        'create' => ['key' => 'tasks.create', 'label' => 'إ�?شاء �?�?�?ة جد�?دة'],
        'update_all' => ['key' => 'tasks.update_all', 'label' => 'تعد�?�? أ�? �?�?�?ة'],
        'delete_all' => ['key' => 'tasks.delete_all', 'label' => 'حذف أ�? �?�?�?ة'],
    ],
    // => ERROR REPORTS
    'error_reports' => [
        'name' => ['key' => 'error_reports', 'label' => 'ص�?اح�?ات ت�?ار�?ر ا�?أخطاء'],
        'page' => ['key' => 'error_reports.page', 'label' => 'صفحة ت�?ار�?ر ا�?أخطاء'],
        'view_all' => ['key' => 'error_reports.view_all', 'label' => 'عرض ج�?�?ع ت�?ار�?ر ا�?أخطاء'],
        'update_all' => ['key' => 'error_reports.update_all', 'label' => 'تحد�?ث حا�?ة ا�?ت�?ر�?ر'],
    ],
    // => BACKUPS
    'backups' => [
        'name' => ['key' => 'backups', 'label' => 'ص�?اح�?ات ا�?�?سخ ا�?احت�?اط�?'],
        'page' => ['key' => 'backups.page', 'label' => 'صفحة ا�?�?سخ ا�?احت�?اط�?'],
        'create' => ['key' => 'backups.create', 'label' => 'تشغ�?�? �?سخة احت�?اط�?ة'],
        'view_all' => ['key' => 'backups.view_all', 'label' => 'عرض ا�?�?سخ ا�?ساب�?ة'],
    ],
    // => QUOTATIONS
    'quotations' => [
        'name' => ['key' => 'quotations', 'label' => 'ص�?اح�?ات عر�?ض ا�?أسعار'],
        'page' => ['key' => 'quotations.page', 'label' => 'صفحة عر�?ض ا�?أسعار'],
        'view_all' => ['key' => 'quotations.view_all', 'label' => 'عرض ج�?�?ع عر�?ض ا�?أسعار'],
        'create' => ['key' => 'quotations.create', 'label' => 'إ�?شاء عرض سعر'],
    ],
    // => ORDERS
    'orders' => [
        'name' => ['key' => 'orders', 'label' => 'ص�?اح�?ات ط�?بات ا�?شراء/ا�?ب�?ع'],
        'page' => ['key' => 'orders.page', 'label' => 'صفحة ا�?ط�?بات'],
        'view_all' => ['key' => 'orders.view_all', 'label' => 'عرض ج�?�?ع ا�?ط�?بات'],
        'create' => ['key' => 'orders.create', 'label' => 'إ�?شاء ط�?ب جد�?د'],
    ],
    'balance' => [
        'name' => ['key' => 'balance', 'label' => 'ص�?اح�?ات إدارة ا�?أرصدة �?ا�?�?ا�?�?ات'],
        'deposit_any' => ['key' => 'balance.deposit_any', 'label' => 'إ�?داع رص�?د �?أ�? �?ستخد�?'],
        'withdraw_any' => ['key' => 'balance.withdraw_any', 'label' => 'سحب رص�?د �?�? أ�? �?ستخد�?'],
        'transfer_any' => ['key' => 'balance.transfer_any', 'label' => 'تح�?�?�? رص�?د �?�? أ�? �?ستخد�?'],
        'deposit' => ['key' => 'balance.deposit', 'label' => 'إجراء إ�?داع رص�?د (شخص�?)'],
        'withdraw' => ['key' => 'balance.withdraw', 'label' => 'إجراء سحب رص�?د (شخص�?)'],
        'transfer' => ['key' => 'balance.transfer', 'label' => '????? ???? (????)'],
        'adjust_balance' => ['key' => 'cashbox.adjust_balance', 'label' => '?????? ?????? ???????'],
    ],
    // => LEGAL DOCUMENTS
    'legal_documents' => [
        'name' => ['key' => 'legal_documents', 'label' => 'ص�?اح�?ات ا�?�?ست�?دات ا�?�?ا�?�?�?�?ة'],
        'page' => ['key' => 'legal_documents.page', 'label' => 'صفحة ا�?�?ست�?دات ا�?�?ا�?�?�?�?ة'],
        'view_all' => ['key' => 'legal_documents.view_all', 'label' => 'عرض ا�?�?ست�?دات ا�?�?ا�?�?�?�?ة'],
        'create' => ['key' => 'legal_documents.create', 'label' => 'إ�?شاء �?س�?دة �?ست�?د جد�?د'],
        'update_all' => ['key' => 'legal_documents.update_all', 'label' => 'تعد�?�? أ�? ص�?اغة إصدار جد�?د'],
        'delete_all' => ['key' => 'legal_documents.delete_all', 'label' => 'حذف �?س�?دة �?ست�?د أ�? إصدار غ�?ر �?�?ش�?ر'],
    ],
    // => HWNIX CASH MODULE
    'hwnix_cash' => [
        'name' => ['key' => 'hwnix_cash', 'label' => 'ص�?اح�?ات �?اش �?�?�?�?س ا�?�?ا�?�?ة'],
        'page' => ['key' => 'hwnix_cash.page', 'label' => 'صفحة أج�?زة �?�?�?د�?�?�? �?اش �?�?�?�?س'],
        'view_all' => ['key' => 'hwnix_cash.view_all', 'label' => 'عرض ج�?�?ع أج�?زة �?شرائح �?اش �?�?�?�?س'],
        'view_self' => ['key' => 'hwnix_cash.view_self', 'label' => 'عرض أج�?زة �?شرائح �?اش �?�?�?�?س ا�?خاصة'],
        'edit_all' => ['key' => 'hwnix_cash.edit_all', 'label' => 'تعد�?�? ج�?�?ع خط�?ط �?شرائح �?اش �?�?�?�?س'],
        'edit_self' => ['key' => 'hwnix_cash.edit_self', 'label' => 'تعد�?�? خط�?ط �?شرائح �?اش �?�?�?�?س ا�?خاصة'],
        'delete_all' => ['key' => 'hwnix_cash.delete_all', 'label' => 'حذف �?إ�?غاء ربط أج�?زة �?اش �?�?�?�?س'],

        // رسائ�? �?اش �?�?�?�?س
        'messages_page' => ['key' => 'hwnix_cash_messages.page', 'label' => 'صفحة سج�?ات رسائ�? �?اش �?�?�?�?س'],
        'messages_view_all' => ['key' => 'hwnix_cash_messages.view_all', 'label' => 'عرض ج�?�?ع رسائ�? �?اش �?�?�?�?س'],
        'messages_view_self' => ['key' => 'hwnix_cash_messages.view_self', 'label' => 'عرض رسائ�? �?اش �?�?�?�?س ا�?خاصة'],
        'messages_create' => ['key' => 'hwnix_cash_messages.create', 'label' => 'إرسا�? رسائ�? جد�?دة عبر �?اش �?�?�?�?س'],

        // �?عا�?�?ات ا�?�?حافظ ا�?إ�?�?تر�?�?�?ة
        'wallet_transactions_page' => ['key' => 'hwnix_cash_wallet_transactions.page', 'label' => 'صفحة �?عا�?�?ات ا�?�?حافظ ا�?إ�?�?تر�?�?�?ة'],
        'wallet_transactions_view_all' => ['key' => 'hwnix_cash_wallet_transactions.view_all', 'label' => 'عرض ج�?�?ع �?عا�?�?ات ا�?�?حافظ'],
        'wallet_transactions_view_self' => ['key' => 'hwnix_cash_wallet_transactions.view_self', 'label' => 'عرض �?عا�?�?ات ا�?�?حافظ ا�?خاصة'],
        'wallet_transactions_create' => ['key' => 'hwnix_cash_wallet_transactions.create', 'label' => 'إضافة �?عا�?�?ة �?حفظة جد�?دة'],
        'wallet_transactions_edit_all' => ['key' => 'hwnix_cash_wallet_transactions.edit_all', 'label' => 'تعد�?�? ج�?�?ع �?عا�?�?ات ا�?�?حافظ'],
        'wallet_transactions_edit_self' => ['key' => 'hwnix_cash_wallet_transactions.edit_self', 'label' => 'تعد�?�? �?عا�?�?ات ا�?�?حافظ ا�?خاصة'],
        'wallet_transactions_delete_all' => ['key' => 'hwnix_cash_wallet_transactions.delete_all', 'label' => 'حذف �?عا�?�?ة �?حفظة إ�?�?تر�?�?�?ة'],
        'wallet_transactions_view_parsed_by' => ['key' => 'hwnix_cash_wallet_transactions.view_parsed_by', 'label' => 'عرض �?�?فذ تح�?�?�? ا�?�?عا�?�?ة'],
        'wallet_transactions_view_parser_stage' => ['key' => 'hwnix_cash_wallet_transactions.view_parser_stage', 'label' => 'عرض �?رح�?ة تح�?�?�? ا�?�?عا�?�?ة'],

        // �?صادر ا�?رسائ�? ا�?�?عت�?دة
        'message_sources_page' => ['key' => 'hwnix_cash_message_sources.page', 'label' => 'صفحة �?صادر ا�?رسائ�? ا�?�?عت�?دة'],
        'message_sources_view_all' => ['key' => 'hwnix_cash_message_sources.view_all', 'label' => 'عرض ج�?�?ع �?صادر ا�?رسائ�? ا�?�?عت�?دة'],
        'message_sources_view_self' => ['key' => 'hwnix_cash_message_sources.view_self', 'label' => 'عرض �?صادر ا�?رسائ�? ا�?خاصة'],
        'message_sources_create' => ['key' => 'hwnix_cash_message_sources.create', 'label' => 'إضافة �?صدر رسائ�? �?عت�?د'],
        'message_sources_edit_all' => ['key' => 'hwnix_cash_message_sources.edit_all', 'label' => 'تعد�?�? ج�?�?ع �?صادر ا�?رسائ�?'],
        'message_sources_edit_self' => ['key' => 'hwnix_cash_message_sources.edit_self', 'label' => 'تعد�?�? �?صادر ا�?رسائ�? ا�?خاصة'],
        'message_sources_delete_all' => ['key' => 'hwnix_cash_message_sources.delete_all', 'label' => 'حذف �?صدر رسائ�? �?عت�?د'],
    ],
    // => CUSTODIES
    'custodies' => [
        'name' => ['key' => 'custodies', 'label' => '??????? ?????'],
        'page' => ['key' => 'custodies.page', 'label' => '?????? ????? ?????'],
        'view_all' => ['key' => 'custodies.view_all', 'label' => '??? ?? ?????'],
        'view_self' => ['key' => 'custodies.view_self', 'label' => '??? ????? ???????'],
        'create' => ['key' => 'custodies.create', 'label' => '????? ????'],
        'refund' => ['key' => 'custodies.refund', 'label' => '??????? ???? ?? ????'],
        'reverse' => ['key' => 'custodies.reverse', 'label' => '??? ??????'],
    ],
    // => OWNER FUNDS
    'owner_fund_transactions' => [
        'name' => ['key' => 'owner_fund_transactions', 'label' => '??????? ??????? ???????'],
        'page' => ['key' => 'owner_fund_transactions.page', 'label' => '?????? ????? ??????? ???????'],
        'view_all' => ['key' => 'owner_fund_transactions.view_all', 'label' => '??? ??????? ???????'],
        'create' => ['key' => 'owner_fund_transactions.create', 'label' => '????? ?????? ????'],
        'reverse' => ['key' => 'owner_fund_transactions.reverse', 'label' => '??? ?????? ????'],
    ],
];





