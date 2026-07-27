<?php

// أنواع القدرات المدعومة في المنصة
namespace Modules\AiPlatform\Enums;

enum Capability: string
{
    // Text
    case TextGenerate   = 'text.generate';
    case TextSummarize  = 'text.summarize';
    case TextTranslate  = 'text.translate';
    case TextClassify   = 'text.classify';
    case TextExtract    = 'text.extract';
    case TextRewrite    = 'text.rewrite';
    case TextQa         = 'text.qa';

    // Image
    case ImageGenerate  = 'image.generate';
    case ImageAnalyze   = 'image.analyze';
    case ImageOcr       = 'image.ocr';
    case ImageCaption   = 'image.caption';

    // Vision
    case VisionExtract  = 'vision.extract';

    // Speech
    case SpeechTts      = 'speech.tts';
    case SpeechStt      = 'speech.stt';

    // Embedding
    case EmbeddingCreate = 'embedding.create';
    case EmbeddingSearch = 'embedding.search';

    // Data
    case DataRerank     = 'data.rerank';

    // Code
    case CodeGenerate   = 'code.generate';
    case CodeReview     = 'code.review';
    case CodeExplain    = 'code.explain';

    public function label(): string
    {
        return match($this) {
            self::TextGenerate    => 'توليد النص',
            self::TextSummarize   => 'تلخيص النص',
            self::TextTranslate   => 'ترجمة النص',
            self::TextClassify    => 'تصنيف النص',
            self::TextExtract     => 'استخراج البيانات',
            self::TextRewrite     => 'إعادة صياغة',
            self::TextQa          => 'الإجابة على الأسئلة',
            self::ImageGenerate   => 'توليد الصور',
            self::ImageAnalyze    => 'تحليل الصور',
            self::ImageOcr        => 'استخراج النص من الصور',
            self::ImageCaption    => 'وصف الصور',
            self::VisionExtract   => 'استخراج بصري',
            self::SpeechTts       => 'تحويل النص لكلام',
            self::SpeechStt       => 'تحويل الكلام لنص',
            self::EmbeddingCreate => 'إنشاء Embedding',
            self::EmbeddingSearch => 'البحث الدلالي',
            self::DataRerank      => 'ترتيب النتائج',
            self::CodeGenerate    => 'توليد الكود',
            self::CodeReview      => 'مراجعة الكود',
            self::CodeExplain     => 'شرح الكود',
        };
    }

    public function type(): string
    {
        return match(true) {
            str_starts_with($this->value, 'text.')      => 'text',
            str_starts_with($this->value, 'image.')     => 'image',
            str_starts_with($this->value, 'vision.')    => 'vision',
            str_starts_with($this->value, 'speech.')    => 'audio',
            str_starts_with($this->value, 'embedding.') => 'embedding',
            str_starts_with($this->value, 'code.')      => 'code',
            default                                      => 'data',
        };
    }
}
