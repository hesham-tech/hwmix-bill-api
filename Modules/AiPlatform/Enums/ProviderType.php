<?php

// أنواع مزودي الخدمة
namespace Modules\AiPlatform\Enums;

enum ProviderType: string
{
    case Llm         = 'llm';         // نماذج اللغة الكبيرة
    case ImageGen    = 'image_gen';   // توليد الصور
    case Tts         = 'tts';         // تحويل النص لكلام
    case Stt         = 'stt';         // تحويل الكلام لنص
    case Ocr         = 'ocr';         // استخراج النص من الصور
    case Embedding   = 'embedding';   // Embeddings
    case Translation = 'translation'; // الترجمة
    case Rerank      = 'rerank';      // إعادة الترتيب

    public function label(): string
    {
        return match($this) {
            self::Llm         => 'نموذج لغوي كبير',
            self::ImageGen    => 'توليد الصور',
            self::Tts         => 'تحويل النص لكلام',
            self::Stt         => 'تحويل الكلام لنص',
            self::Ocr         => 'استخراج النص من الصور',
            self::Embedding   => 'Embeddings',
            self::Translation => 'ترجمة',
            self::Rerank      => 'إعادة الترتيب',
        };
    }
}
