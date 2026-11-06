<?php
namespace Modules\Media\Services;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Validation\ValidationException;
final class SvgSanitizer {
    public function clean(string $source): string {
        $sanitizer=new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $clean=$sanitizer->sanitize($source);
        if($clean===false || !preg_match('/<svg(?:\s|>)/i',$clean))
            throw ValidationException::withMessages(['file'=>'ملف SVG غير صالح أو يتضمن بنية غير مسموحة.']);
        return $clean;
    }
}
