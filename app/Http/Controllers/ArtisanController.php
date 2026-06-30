<?php

namespace App\Http\Controllers;

use Throwable;
use Illuminate\Http\JsonResponse;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Support\Facades\DB;

/**
 * كلاس للتحكم في أوامر Artisan وعمليات النظام الحساسة.
 */
class ArtisanController extends Controller
{
    public function health(): JsonResponse
    {
        return api_success([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ], 'System health check passed.');
    }

    /**
     * ميثود خاصة للتحقق من أن المستخدم هو مدير النظام.
     */
    private function authorizeAdmin()
    {
        $user = auth()->user();
        if (!$user || !$user->hasPermissionTo(perm_key('admin.super'))) {
            return false;
        }
        return true;
    }

    /**
     * تشغيل أمر composer dump-autoload.
     * @return \Illuminate\Http\JsonResponse
     * عمل اوتو لود للملفات 
     */
    public function runComposerDump(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            $output = shell_exec('composer2 dump-autoload 2>&1');
            // $output = shell_exec('composer dump-autoload 2>&1');
            return api_success(['output' => $output], 'تم تنفيذ أمر Composer dump-autoload بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * // تشغيل الميجريشن (تحديث الجداول دون مسح البيانات)
     */
    public function migrate(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return api_success(['output' => $output], 'تم تنفيذ الميجريشن بنجاح لتحديث هيكل قاعدة البيانات.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * // ميجريشن ريفرش وعمل سيدرنج لقاعدة البيانات من جديد
     */
    public function migrateAndSeed(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $migrateOutput = Artisan::output(); // التقاط مخرجات الهجرة
            Artisan::call('db:seed', ['--force' => true]);
            $seedOutput = Artisan::output(); // التقاط مخرجات التغذية

            return api_success([
                'migrate_output' => $migrateOutput,
                'seed_output' => 'تم تنفيذ Seeders بنجاح.',
            ], 'تم ترحيل قاعدة البيانات وتغذيتها بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تشغيل RolesAndPermissionsSeeder.
     * @return \Illuminate\Http\JsonResponse
     */
    public function seedRolesAndPermissions(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\RolesAndPermissionsSeeder',
                '--force' => true
            ]);
            return api_success([], 'تم تنفيذ RolesAndPermissionsSeeder بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تشغيل PermissionsSeeder (دالة مكررة، تم توحيدها).
     * @return \Illuminate\Http\JsonResponse
     */
    public function PermissionsSeeder(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\PermissionsSeeder',
                '--force' => true
            ]);
            return api_success([], 'تم تنفيذ PermissionsSeeder بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
    public function DatabaseSeeder(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\DatabaseSeeder',
                '--force' => true
            ]);
            return api_success([], 'تم تنفيذ DatabaseSeeder بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * مسح جميع الكاشات وإعادة بنائها.
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearAllCache(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            // تنظيف الكاشات
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('clear-compiled');

            // تنظيف كاش Spatie لو موجود
            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            // إعادة بناء الكاشات المهمة
            Artisan::call('config:cache');
            Artisan::call('route:cache');

            return api_success([
                'cache' => 'تم مسح الكاش',
                'config' => 'تم مسح وإعادة بناء كاش الإعدادات',
                'route' => 'تم مسح وإعادة بناء كاش المسارات',
                'view' => 'تم مسح كاش العروض',
                'compiled' => 'تم مسح الملفات المترجمة',
                'permissions' => 'تم مسح كاش صلاحيات Spatie (إذا كان موجودًا)',
            ], 'تم مسح جميع الكاشات وإعادة بنائها بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تصدير البيانات وتوليد السيدرز.
     */
    public function generateBackup(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            $service = new DatabaseBackupService();
            $report = $service->exportDataAndGenerateSeeders();

            $message = empty($report['errors']) ? 'تم اكتمال النسخ الاحتياطي بنجاح.' : 'تم اكتمال النسخ الاحتياطي مع بعض الأخطاء.';
            $status = empty($report['errors']) ? 'نجاح' : 'تحذير';

            return api_success($report, $message);
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تشغيل السيدرز التي تم توليدها.
     */
    public function applyBackup(): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return api_forbidden('غير مصرح لك بالوصول إلى أدوات النظام الحساسة.');
        }

        try {
            $service = new DatabaseBackupService();
            $service->runBackupSeeders();

            return api_success([], 'تم تشغيل السيدرز الاحتياطية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
