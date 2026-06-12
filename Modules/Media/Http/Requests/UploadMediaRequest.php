<?php

namespace Modules\Media\Http\Requests;

//   طلب للتحقق من صحة الملف المرفوع وحجمه وامتداداته المسموحة.

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp,svg,pdf,doc,docx,xls,xlsx,txt|max:10240', // 10MB Max
        ];
    }
}
