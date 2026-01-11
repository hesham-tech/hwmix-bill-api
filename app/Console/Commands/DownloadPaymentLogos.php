<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadPaymentLogos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:download-logos {--clear : Clear the directory before downloading}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تحميل شعارات طرق الدفع وتخزينها في مجلد seeders في المشروع';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // القائمة مع روابط مباشرة للشعارات (متوافقة مع PaymentMethodSeeder)
        $methods = [
            ['name' => 'نقدي', 'code' => 'cash', 'url' => 'https://cdn-icons-png.flaticon.com/512/2331/2331714.png'],
            ['name' => 'بطاقة ائتمان', 'code' => 'credit_card', 'url' => 'https://cdn-icons-png.flaticon.com/512/1611/1611154.png'],
            ['name' => 'تحويل بنكي', 'code' => 'bank_transfer', 'url' => 'https://cdn-icons-png.flaticon.com/512/1000/1000997.png'],
            ['name' => 'باي بال', 'code' => 'paypal', 'url' => 'https://cdn-icons-png.flaticon.com/512/174/174861.png'],
            ['name' => 'فودافون كاش', 'code' => 'vodafone_cash', 'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Vodafone_Cash.png'],
            ['name' => 'اتصالات كاش', 'code' => 'etisalat_cash', 'url' => 'https://seeklogo.com/images/E/etisalat-logo-04D5417B60-seeklogo.com.png'],
            ['name' => 'أورنج كاش', 'code' => 'orange_cash', 'url' => 'https://upload.wikimedia.org/wikipedia/commons/c/c8/Orange_logo.svg'],
            ['name' => 'إنستاباي', 'code' => 'instapay', 'url' => 'https://upload.wikimedia.org/wikipedia/commons/e/ea/InstaPay_Logo.png'],
            ['name' => 'فوري', 'code' => 'fawry', 'url' => 'https://fawry.com/wp-content/uploads/2019/08/fawry-logo.png'],
            ['name' => 'فاليو', 'code' => 'valu', 'url' => 'https://www.valu.com.eg/assets/images/valu-logo.png'],
            ['name' => 'سمبل', 'code' => 'sympl', 'url' => 'https://sympl.ai/wp-content/uploads/2021/10/sympl-logo.png'],
        ];

        // المسار في Laravel storage
        $directory = 'seeders/payment-methods';

        // إنشاء المجلد إذا لم يكن موجوداً
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // تنظيف المجلد إذا طلب المستخدم ذلك
        if ($this->option('clear')) {
            $this->info('🗑️ جاري تنظيف المجلد...');
            $files = Storage::disk('public')->files($directory);
            Storage::disk('public')->delete($files);
        }

        $this->info('⏳ جاري تحميل الشعارات...');

        foreach ($methods as $method) {
            try {
                $this->line("⬇️ محاولة تحميل: {$method['name']}...");

                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])->timeout(30)->get($method['url']);

                if ($response->successful()) {
                    // تحديد الامتداد بناءً على الرابط
                    $extension = pathinfo(parse_url($method['url'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';

                    // حماية الأكواد لتكون مناسبة لأسماء الملفات
                    $filename = Str::lower($method['code']) . '.' . $extension;

                    Storage::disk('public')->put($directory . '/' . $filename, $response->body());

                    $this->info("✅ تم حفظ: {$method['name']} باسم {$filename}");
                } else {
                    // محاولة بديلة إذا كان الموقع يحظر الطلبات المباشرة
                    $this->error("❌ فشل تحميل: {$method['name']} (كود الاستجابة: " . $response->status() . ")");
                }
            } catch (\Exception $e) {
                $this->error("⚠️ خطأ في {$method['name']}: " . $e->getMessage());
            }
        }

        $fullPath = storage_path('app/public/' . $directory);
        $this->info("✨ اكتملت العملية! ستجد الصور في: $fullPath");
    }
}
