<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Exception;

class BackupService
{
    /**
     * Run a manual backup.
     */
    public function runBackup($type = 'manual')
    {
        $backupRecord = Backup::create([
            'status' => 'pending',
            'type' => $type,
        ]);

        try {
            // Check if we should include files
            $onlyDb = !BackupSetting::getVal('backup_include_files', false);

            $options = [];
            if ($onlyDb) {
                $options['--only-db'] = true;
            }

            Artisan::call('backup:run', $options);

            // Note: Artisan::call is synchronous. 
            // In a real production environment with large DBs, this should be a Job.

            $output = Artisan::output();

            // We need to find the latest file created in the backup disk
            $this->updateLastBackupInfo($backupRecord);

            return [
                'success' => true,
                'record' => $backupRecord,
                'output' => $output
            ];

        } catch (Exception $e) {
            $backupRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update the backup record with file info.
     */
    private function updateLastBackupInfo(Backup $record)
    {
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $path = config('backup.backup.name') . '/';

        $files = Storage::disk($disk)->files($path);

        if (empty($files)) {
            $record->update(['status' => 'failed', 'error_message' => 'Backup file not found after execution.']);
            return;
        }

        // Sort by last modified
        usort($files, function ($a, $b) use ($disk) {
            return Storage::disk($disk)->lastModified($b) - Storage::disk($disk)->lastModified($a);
        });

        $latestFile = $files[0];
        $size = Storage::disk($disk)->size($latestFile);

        $record->update([
            'filename' => basename($latestFile),
            'disk' => $disk,
            'size_bytes' => $size,
            'status' => 'success',
            'completed_at' => now(),
        ]);
    }

    /**
     * Restore from a specific backup file.
     */
    public function restore($filename)
    {
        // 1. Validate file
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $path = config('backup.backup.name') . '/' . $filename;

        if (!Storage::disk($disk)->exists($path)) {
            throw new \Exception("ملف النسخ الاحتياطي غير موجود: $filename");
        }

        // 2. Maintenance Mode
        Artisan::call('down', [
            '--refresh' => 15,
            '--retry' => 60,
            '--secret' => 'restore-in-progress'
        ]);

        $tempPath = storage_path('app/temp-restore-' . time());
        File::makeDirectory($tempPath, 0755, true);

        try {
            // 3. Extract Backup
            $zipPath = Storage::disk($disk)->path($path);
            $zip = new \ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $zip->extractTo($tempPath);
                $zip->close();
            } else {
                throw new \Exception("فشل فتح الملف المضغوط (ZIP)");
            }

            // 4. Import Database
            $dbFiles = File::allFiles($tempPath . '/db-dumps');
            if (empty($dbFiles)) {
                // Fallback: search for any .sql file
                $allFiles = File::allFiles($tempPath);
                foreach ($allFiles as $file) {
                    if ($file->getExtension() === 'sql') {
                        $dbFiles[] = $file;
                        break;
                    }
                }
            }

            if (empty($dbFiles)) {
                throw new \Exception("لم يتم العثور على قاعدة البيانات داخل النسخة الاحتياطية");
            }

            $sqlFile = $dbFiles[0]->getRealPath();
            $this->importSql($sqlFile);

            // 5. Restore Storage Files (Optional: only if backups include files)
            // Note: This replaces current storage files.
            if (File::isDirectory($tempPath . '/storage')) {
                // Logic to sync storage could be added here
            }

            // 6. Recovery
            Artisan::call('up');

            // Clean up
            File::deleteDirectory($tempPath);

            return true;
        } catch (\Exception $e) {
            Artisan::call('up');
            if (File::exists($tempPath)) {
                File::deleteDirectory($tempPath);
            }
            throw $e;
        }
    }

    /**
     * Import SQL file into the database.
     */
    private function importSql($path)
    {
        $dbConfig = config('database.connections.mysql');

        $host = $dbConfig['host'];
        $port = $dbConfig['port'];
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Get binary path (same as mysqldump in config/database.php)
        $binaryPath = env('DUMP_BINARY_PATH');
        $mysqlBinary = $binaryPath ? rtrim($binaryPath, '/\\') . DIRECTORY_SEPARATOR . 'mysql' : 'mysql';

        // Build command
        // Note: For Hostinger/Linux, we use mysql command
        $command = sprintf(
            '%s --host=%s --port=%s --user=%s --password=%s %s < %s',
            escapeshellarg($mysqlBinary),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($path)
        );

        $output = [];
        $returnVar = 0;

        // Execute command
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            // Log output for debugging
            logger()->error('Database restore failed', ['output' => $output, 'command' => $command]);
            throw new \Exception("فشل استيراد قاعدة البيانات. كود الخطأ: $returnVar");
        }
    }
}
