<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@admin.com')->first();
if ($user) {
    $match = Illuminate\Support\Facades\Hash::check('12345678', $user->password);
    echo "Password '12345678' check result: " . ($match ? "MATCH" : "MISMATCH") . "\n";
    
    // محاكاة تسجيل الدخول للـ Agent يدوياً لمعرفة الخطأ الدقيق
    $loginField = 'email';
    $loginValue = 'admin@admin.com';
    $password = '12345678';
    
    $attempt = Illuminate\Support\Facades\Auth::attempt([$loginField => $loginValue, 'password' => $password]);
    echo "Auth::attempt result: " . ($attempt ? "SUCCESS" : "FAIL") . "\n";
} else {
    echo "User not found\n";
}
