<?php
namespace Modules\AiPlatform\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AiAdminScope
{
    public static function company(Request $request): int
    {
        $id = $request->user()->active_company_id;
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0
            || !DB::table('companies')->where('id', (int) $id)->whereNull('deleted_at')->exists()) {
            throw new HttpResponseException(new JsonResponse(['success' => false, 'message' => 'اختر شركة نشطة صالحة أولًا.'], 422));
        }
        return (int) $id;
    }

    public static function accountOrPrompt(Request $request): ?int
    {
        // Only requests that already passed EnsureAiAdministrator can select global records.
        return $request->boolean('is_system') ? null : self::company($request);
    }

    public static function record($query, $id)
    {
        $record = $query->find($id);
        if (!$record) throw new HttpResponseException(new JsonResponse(['success' => false, 'message' => 'السجل غير متاح.'], 404));
        return $record;
    }
}
