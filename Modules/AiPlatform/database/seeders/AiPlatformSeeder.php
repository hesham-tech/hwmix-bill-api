<?php

namespace Modules\AiPlatform\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\AiPlatform\Enums\Capability;

class AiPlatformSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إدراج القدرات الأساسية (Capabilities)
        foreach (Capability::cases() as $cap) {
            DB::table('ai_capabilities')->updateOrInsert(
                ['key' => $cap->value],
                [
                    'label'       => $cap->label(),
                    'type'        => $cap->type(),
                    'description' => "قدرة {$cap->label()} المستقلة",
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }

        // 2. إدراج المزودين الأساسيين الاربعة
        $providersList = [
            'gemini' => [
                'key'          => 'gemini',
                'label'        => 'Google Gemini',
                'type'         => 'llm',
                'driver_class' => 'Modules\AiPlatform\Drivers\GeminiDriver',
                'base_url'     => 'https://generativelanguage.googleapis.com',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            'openai' => [
                'key'          => 'openai',
                'label'        => 'OpenAI',
                'type'         => 'llm',
                'driver_class' => 'Modules\AiPlatform\Drivers\OpenAiDriver',
                'base_url'     => 'https://api.openai.com/v1',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            'anthropic' => [
                'key'          => 'anthropic',
                'label'        => 'Anthropic Claude',
                'type'         => 'llm',
                'driver_class' => 'Modules\AiPlatform\Drivers\OpenAiDriver',
                'base_url'     => 'https://api.anthropic.com/v1',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            'ollama' => [
                'key'          => 'ollama',
                'label'        => 'Ollama Local',
                'type'         => 'llm',
                'driver_class' => 'Modules\AiPlatform\Drivers\OpenAiDriver',
                'base_url'     => 'http://localhost:11434/v1',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ];

        $providers = [];
        foreach ($providersList as $k => $data) {
            DB::table('ai_providers')->updateOrInsert(['key' => $k], $data);
            $providers[$k] = DB::table('ai_providers')->where('key', $k)->value('id');
        }

        // 3. إدراج النماذج الأولى الافتراضية
        $models = [
            [
                'ai_provider_id'     => $providers['gemini'],
                'model_id'           => 'gemini-pro-latest',
                'label'              => 'Gemini Pro Latest',
                'max_context_tokens' => 2097152,
                'max_output_tokens'  => 8192,
                'input_price_per_1k' => 0.00125,
                'output_price_per_1k'=> 0.00375,
                'is_active'          => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'ai_provider_id'     => $providers['gemini'],
                'model_id'           => 'gemini-flash-latest',
                'label'              => 'Gemini Flash Latest',
                'max_context_tokens' => 1048576,
                'max_output_tokens'  => 8192,
                'input_price_per_1k' => 0.000075,
                'output_price_per_1k'=> 0.000300,
                'is_active'          => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'ai_provider_id'     => $providers['openai'],
                'model_id'           => 'gpt-4o',
                'label'              => 'GPT-4o',
                'max_context_tokens' => 128000,
                'max_output_tokens'  => 16384,
                'input_price_per_1k' => 0.00500,
                'output_price_per_1k'=> 0.01500,
                'is_active'          => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'ai_provider_id'     => $providers['openai'],
                'model_id'           => 'gpt-4o-mini',
                'label'              => 'GPT-4o Mini',
                'max_context_tokens' => 128000,
                'max_output_tokens'  => 16384,
                'input_price_per_1k' => 0.000150,
                'output_price_per_1k'=> 0.000600,
                'is_active'          => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'ai_provider_id'     => $providers['anthropic'],
                'model_id'           => 'claude-3-5-sonnet',
                'label'              => 'Claude 3.5 Sonnet',
                'max_context_tokens' => 200000,
                'max_output_tokens'  => 8192,
                'input_price_per_1k' => 0.00300,
                'output_price_per_1k'=> 0.01500,
                'is_active'          => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ];

        // 4. ربط القدرة بالنموذج لكل النماذج
        foreach ($models as $m) {
            DB::table('ai_models')->updateOrInsert(['model_id' => $m['model_id']], $m);
            $modelId = DB::table('ai_models')->where('model_id', $m['model_id'])->value('id');
            DB::table('ai_model_capabilities')->updateOrInsert([
                'ai_model_id'       => $modelId,
                'ai_capability_key' => Capability::TextGenerate->value,
            ], [
                'created_at' => now(),
            ]);
        }

        // 5. إنشاء Prompt افتراضي لتوليد وصف المنتجات
        DB::table('ai_prompts')->updateOrInsert([
            'company_id' => 1,
            'key'        => 'product.description.generate',
        ], [
            'type'            => 'text',
            'label'           => 'قالب توليد وصف المنتجات التسويقي',
            'capability_key'  => Capability::TextGenerate->value,
            'current_version' => 1,
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $promptId = DB::table('ai_prompts')->where('key', 'product.description.generate')->value('id');

        DB::table('ai_prompt_versions')->updateOrInsert([
            'ai_prompt_id' => $promptId,
            'version'      => 1,
            'locale'       => 'ar',
        ], [
            'company_id'   => 1,
            'template'     => "أنت كاتب محتوى احترافي لمتجر إلكتروني.\nالمطلوب هو إنشاء وصف احترافي ودقيق لمنتج ليتم حفظه مباشرة داخل قاعدة بيانات المتجر.\n\nبيانات المنتج المتاحة:\n- اسم المنتج: {{ product_name }}\n- البيانات والخصائص المتاحة: {{ features }}\n\nالتعليمات الصارمة لمنع التخمين أو التأليف (Anti-Hallucination Rules):\n1. التزم حصرياً بالبيانات المتاحة الممررة أعلاه فقط.\n2. يُمنع منعاً باتاً تخمين، اختراع، أو إضافة أي مواصفات أو معلومات غير موجودة في البيانات الممررة (مثل: بلد التصنيع، المقاسات، نوع الخامة، تعليمات الغسيل، أو ضمانات وهمية) إذا لم تكن مذكورة صراحة.\n3. إذا كانت البيانات المتاحة قليلة (مثل اسم المنتج فقط)، اكتب وصفاً تجارياً عاماً وموجزاً جداً (سطرين أو 3 أسطر فقط) يناسب الاسم، دون ذكر أي مواصفات تفصيلية لم تُذكر.\n4. كلما توفرت بيانات إضافية ممررة (مثل الماركة/البراند، الخصائص، أو الألوان)، استخدمها لبناء الوصف وتوضيح قيمتها للمشتري.\n5. أرجع نص الوصف الخالص المباشر فقط الصالح للحفظ فوراً في حقل الوصف.\n6. ممنوع استخدام: (إليك - العنوان - الوصف - بالطبع - *** - ## - ### - * - اطلب الآن - تسوق الآن - لا تفوت الفرصة).\n7. ابدأ مباشرة بنص الوصف الأول وانتهِ عند آخر جملة من الوصف دون كتابة أي تعليقات جانبية.",
            'notes'        => 'الإصدار الأول الجاهز للاستخدام',
            'is_active'    => true,
            'created_at'   => now(),
        ]);

        DB::table('ai_prompt_variables')->updateOrInsert([
            'ai_prompt_id' => $promptId,
            'name'         => 'product_name',
        ], [
            'type'         => 'string',
            'is_required'  => true,
            'created_at'   => now(),
        ]);

        DB::table('ai_prompt_variables')->updateOrInsert([
            'ai_prompt_id' => $promptId,
            'name'         => 'features',
        ], [
            'type'         => 'string',
            'is_required'  => true,
            'created_at'   => now(),
        ]);
    }
}
