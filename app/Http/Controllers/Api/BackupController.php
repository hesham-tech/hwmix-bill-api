<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSystemBackupAdministrator;
use App\Models\{Backup, BackupSetting};
use App\Services\{BackupConfirmation, BackupService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controllers\{HasMiddleware, Middleware};
use Illuminate\Support\Facades\{DB, Hash, Log, Storage, Validator};

class BackupController extends Controller implements HasMiddleware
{
    private const SETTINGS = [
        'backup_frequency' => ['type' => 'string', 'default' => 'daily'],
        'backup_time' => ['type' => 'string', 'default' => '03:00'],
        'backup_retention_days' => ['type' => 'integer', 'default' => 7],
        'backup_include_files' => ['type' => 'boolean', 'default' => false],
    ];
    private const FIELDS = ['id', 'filename', 'size_bytes', 'status', 'type', 'completed_at', 'created_at'];

    public function __construct(private BackupService $backupService, private BackupConfirmation $confirmation) {}

    public static function middleware(): array
    {
        return ['auth:sanctum', EnsureSystemBackupAdministrator::class,
            new Middleware('throttle:5,1', only: ['confirmPassword'])];
    }

    private function audit(Request $request, string $action, ?int $id = null, string $outcome = 'allowed'): void
    {
        Log::notice('system_backup.action', ['actor_id' => $request->user()->getAuthIdentifier(),
            'action' => $action, 'backup_id' => $id, 'outcome' => $outcome]);
    }

    private function invalid(string $message, int $status = 403): JsonResponse
    {
        // Never send request bodies, archive paths or service exceptions through generic error logging.
        return new JsonResponse(['status' => false, 'message' => $message], $status);
    }

    public function index()
    {
        return api_success(Backup::query()->select(self::FIELDS)->latest()->paginate(15));
    }

    public function confirmPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|max:1024', 'action' => 'required|in:download,restore',
            'backup_id' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) return $this->invalid('بيانات تأكيد الهوية غير صالحة.', 422);
        if (!Hash::check($request->input('password'), $request->user()->getAuthPassword())) {
            $this->audit($request, 'confirm', null, 'denied');
            return $this->invalid('كلمة المرور غير صحيحة.', 422);
        }
        return api_success(['confirmation_token' => $this->confirmation->issue($request, $request->input('action'), (int) $request->input('backup_id')),
            'expires_in' => BackupConfirmation::TTL]);
    }

    public function run(Request $request)
    {
        $this->audit($request, 'run');
        try {
            $result = $this->backupService->runBackup('manual');
            return api_success(['success' => $result['success'] ?? false,
                'record' => isset($result['record']) ? $result['record']->only(self::FIELDS) : null], 'تم تنفيذ طلب النسخ الاحتياطي.');
        } catch (\Throwable) {
            $this->audit($request, 'run', null, 'failed');
            return $this->invalid('تعذر تنفيذ النسخ الاحتياطي. راجع سجل التشغيل.', 500);
        }
    }

    private function backup($id): ?Backup
    {
        return Backup::find($id);
    }

    public function download(Request $request, $id)
    {
        if (!$this->confirmation->valid($request, 'download', (int) $id)) return $this->invalid('أكد كلمة المرور مجددًا لتنزيل نسخة النظام.');
        $backup = $this->backup($id);
        if (!$backup || $backup->status !== 'success' || !$backup->filename) return $this->invalid('النسخة غير متاحة.', 404);
        $path = config('backup.backup.name').'/'.$backup->filename;
        if (!Storage::disk($backup->disk)->exists($path)) return $this->invalid('النسخة غير متاحة.', 404);
        $this->audit($request, 'download', (int) $id);
        return Storage::disk($backup->disk)->download($path, 'backup-'.(int) $id.'.zip', [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $backup = $this->backup($id);
        if (!$backup) return $this->invalid('النسخة غير متاحة.', 404);
        $this->audit($request, 'delete', (int) $id);
        $path = config('backup.backup.name').'/'.$backup->filename;
        if ($backup->filename && Storage::disk($backup->disk)->exists($path)) Storage::disk($backup->disk)->delete($path);
        $backup->delete();
        return api_success(null, 'تم حذف النسخة الاحتياطية.');
    }

    private function settings(): array
    {
        $stored = BackupSetting::query()->whereIn('key', array_keys(self::SETTINGS))->pluck('value', 'key');
        $result = [];
        foreach (self::SETTINGS as $key => $spec) {
            $value = $stored[$key] ?? $spec['default'];
            if ($spec['type'] === 'boolean') $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            elseif ($spec['type'] === 'integer') $value = (int) $value;
            $result[] = ['key' => $key, 'value' => $value, 'type' => $spec['type']];
        }
        return $result;
    }

    public function getSettings()
    {
        return api_success($this->settings());
    }

    public function updateSettings(Request $request)
    {
        if (array_diff(array_keys($request->all()), array_keys(self::SETTINGS))) return $this->invalid('يوجد إعداد غير مسموح.', 422);
        $validator = Validator::make($request->all(), [
            'backup_frequency' => 'sometimes|required|in:daily,weekly,monthly,none',
            'backup_time' => 'sometimes|required|date_format:H:i',
            'backup_retention_days' => 'sometimes|required|integer|min:1|max:365',
            'backup_include_files' => 'sometimes|required|boolean',
        ]);
        if ($validator->fails()) return $this->invalid('قيم إعدادات النسخ غير صالحة.', 422);
        $this->audit($request, 'settings.update');
        DB::transaction(function () use ($request) {
            foreach ($request->all() as $key => $value) {
                $type = self::SETTINGS[$key]['type'];
                if ($type === 'boolean') $value = $value ? '1' : '0';
                BackupSetting::updateOrCreate(['key' => $key], ['value' => (string) $value, 'type' => $type]);
            }
        });
        return api_success($this->settings(), 'تم تحديث الإعدادات.');
    }

    public function restore(Request $request, $id)
    {
        if (!$this->confirmation->valid($request, 'restore', (int) $id)) return $this->invalid('أكد كلمة المرور مجددًا للاستعادة.');
        $supplied = $request->input('token');
        $stored = BackupSetting::getVal('backup_restore_token');
        if (!is_string($supplied) || !is_string($stored) || $stored === '' || !hash_equals($stored, $supplied)) {
            $this->audit($request, 'restore', (int) $id, 'denied');
            return $this->invalid('رمز الاستعادة غير صحيح.');
        }
        $backup = $this->backup($id);
        if (!$backup || $backup->status !== 'success' || !$backup->filename) return $this->invalid('النسخة غير متاحة.', 404);
        $this->audit($request, 'restore', (int) $id);
        try {
            $this->backupService->restore($backup->filename);
            return api_success(null, 'تمت استعادة البيانات.');
        } catch (\Throwable) {
            $this->audit($request, 'restore', (int) $id, 'failed');
            return $this->invalid('تعذرت الاستعادة. راجع سجل التشغيل.', 500);
        }
    }
}
