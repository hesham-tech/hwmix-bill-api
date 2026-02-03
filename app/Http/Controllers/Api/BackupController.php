<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BackupController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * List all backup history.
     */
    public function index()
    {
        $backups = Backup::latest()->paginate(15);
        return api_success($backups);
    }

    /**
     * Run a manual backup.
     */
    public function run()
    {
        try {
            $result = $this->backupService->runBackup('manual');
            return api_success($result, 'بدأت عملية النسخ الاحتياطي بنجاح');
        } catch (\Exception $e) {
            return api_error('فشلت عملية النسخ الاحتياطي: ' . $e->getMessage());
        }
    }

    /**
     * Download a specific backup file.
     */
    public function download($id)
    {
        $backup = Backup::findOrFail($id);
        $path = config('backup.backup.name') . '/' . $backup->filename;

        if (!Storage::disk($backup->disk)->exists($path)) {
            return api_error('ملف النسخ الاحتياطي غير موجود');
        }

        return Storage::disk($backup->disk)->download($path);
    }

    /**
     * Delete a backup record and file.
     */
    public function destroy($id)
    {
        $backup = Backup::findOrFail($id);
        $path = config('backup.backup.name') . '/' . $backup->filename;

        if (Storage::disk($backup->disk)->exists($path)) {
            Storage::disk($backup->disk)->delete($path);
        }

        $backup->delete();

        return api_success(null, 'تم حذف النسخة الاحتياطية بنجاح');
    }

    /**
     * Get backup settings.
     */
    public function getSettings()
    {
        return api_success(BackupSetting::all());
    }

    /**
     * Update backup settings.
     */
    public function updateSettings(Request $request)
    {
        $settings = $request->all();

        foreach ($settings as $key => $value) {
            BackupSetting::where('key', $key)->update(['value' => $value]);
        }

        return api_success(BackupSetting::all(), 'تم تحديث الإعدادات بنجاح');
    }

    /**
     * Restore from backup (High Security).
     */
    public function restore(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return api_error('كود التحقق مطلوب لإتمام عملية الاسترجاع');
        }

        $backup = Backup::findOrFail($id);
        $storedToken = BackupSetting::getVal('backup_restore_token');

        if ($request->token !== $storedToken) {
            return api_error('كود التحقق غير صحيح');
        }

        try {
            $this->backupService->restore($backup->filename);
            return api_success(null, 'تمت استعادة البيانات بنجاح');
        } catch (\Exception $e) {
            return api_error('فشلت عملية الاستعادة: ' . $e->getMessage());
        }
    }
}
