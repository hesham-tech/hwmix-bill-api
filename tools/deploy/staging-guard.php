<?php

// Fail closed before any migration, seeder, or cache command.
require __DIR__ . '/../../vendor/autoload.php';

$root = realpath(__DIR__ . '/../..');
$prefix = '/home/u715355537/apps/hwnix-bill-staging/releases/';
if (!$root || !str_starts_with($root, $prefix)
    || !preg_match('/^[a-f0-9]{40}$/D', substr($root, strlen($prefix)))) {
    throw new RuntimeException('Refusing to run outside a staging release.');
}
if (realpath($root . '/.env') !== '/home/u715355537/apps/hwnix-bill-staging/shared/.env') {
    throw new RuntimeException('Staging environment file is not the expected private file.');
}
$env = Dotenv\Dotenv::parse(file_get_contents($root . '/.env'));
foreach ([
    'APP_ENV' => 'staging', 'APP_URL' => 'https://staging-api.hwnix.com',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
    'DB_DATABASE' => 'u715355537_stagingdb', 'DB_USERNAME' => 'u715355537_staginguser',
    'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file',
    'QUEUE_CONNECTION' => 'database', 'MAIL_MAILER' => 'array',
] as $name => $expected) {
    if (($env[$name] ?? null) !== $expected) {
        throw new RuntimeException('Staging isolation check failed for ' . $name);
    }
}
if (!empty($env['DB_URL']) || ($env['APP_DEBUG'] ?? '') !== 'false') {
    throw new RuntimeException('Unexpected database URL or public debug configuration.');
}
if (($argv[1] ?? '') === 'env') {
    echo "Private staging environment verified.\n";
    exit(0);
}
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
if (!$app->environment('staging') || config('database.default') !== 'mysql'
    || $connection->getConfig('database') !== 'u715355537_stagingdb'
    || $connection->getConfig('username') !== 'u715355537_staginguser'
    || $connection->selectOne('SELECT DATABASE() AS db')->db !== 'u715355537_stagingdb') {
    throw new RuntimeException('Refusing deployment commands on an unexpected database.');
}
switch ($argv[1] ?? 'assert') {
    case 'assert':
        echo "Actual staging database connection verified.\n";
        break;
    case 'legal':
        if (Illuminate\Support\Facades\DB::table('legal_documents')->count() === 0) {
            if (Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => Modules\Legal\database\seeders\LegalDatabaseSeeder::class, '--force' => true,
            ]) !== 0) {
                throw new RuntimeException('Staging legal document seeder failed.');
            }
        }
        break;
    case 'protect-bootstrap':
        $owner = App\Models\User::withoutGlobalScopes()->where('username', 'system_owner')->first();
        if ($owner && Illuminate\Support\Facades\Hash::check('12345678', $owner->password)) {
            $password = $env['STAGING_BOOTSTRAP_PASSWORD'] ?? null;
            if (!$password || strlen($password) < 32) {
                throw new RuntimeException('A private random staging bootstrap password is required.');
            }
            $owner->forceFill(['password' => Illuminate\Support\Facades\Hash::make($password)])->saveQuietly();
        }
        break;
    default:
        throw new InvalidArgumentException('Unknown staging guard operation.');
}
