<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ✅ إرجاع استجابة ناجحة
 */
if (!function_exists('api_success')) {
    function api_success($data = [], string $message = 'تم بنجاح', int $code = 200): JsonResponse
    {
        $response = [
            'status' => true,
            'message' => $message,
            'data' => [],
        ];

        // ✅ إضافة سياق المستخدم الموثق (الرصيد المحدث للشركة النشطة)
        if (auth()->check()) {
            $response['auth'] = [
                'user' => [
                    'id' => auth()->id(),
                    'balance' => (float) auth()->user()->balance,
                ]
            ];
        }

        // ✅ إذا كانت البيانات Paginator عادي (للبجينيشن)
        if ($data instanceof AbstractPaginator) {
            $response['data'] = $data->items();
            $response['total'] = $data->total(); // مهم لـ v-data-table-server

            // ✅ إضافة بيانات الترقيم القياسية
            $response['meta'] = [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ];

            // ✅ إضافة روابط التنقل القياسية
            $response['links'] = [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ];

            return response()->json($response, $code);
        }

        // ✅ إذا كانت ResourceCollection (قد تكون مع Pagination)
        if ($data instanceof ResourceCollection) {
            $original = $data->resource;

            if ($original instanceof AbstractPaginator) {
                $response['data'] = $data->collection;
                $response['total'] = $original->total();

                // ✅ إضافة بيانات الترقيم القياسية من البجينيشن الأصلي
                $response['meta'] = [
                    'current_page' => $original->currentPage(),
                    'last_page' => $original->lastPage(),
                    'per_page' => $original->perPage(),
                    'total' => $original->total(),
                    'from' => $original->firstItem(),
                    'to' => $original->lastItem(),
                ];

                // ✅ إضافة روابط التنقل من البجينيشن الأصلي
                $response['links'] = [
                    'first' => $original->url(1),
                    'last' => $original->url($original->lastPage()),
                    'prev' => $original->previousPageUrl(),
                    'next' => $original->nextPageUrl(),
                ];
            } else {
                $response['data'] = $data->collection;
                $response['total'] = $data->count();
            }

            return response()->json($response, $code);
        }

        // ✅ إذا كانت JsonResource (عنصر واحد)
        if ($data instanceof JsonResource) {
            $response['data'] = $data;
            return response()->json($response, $code);
        }

        // ✅ لو Array أو Collection عادية
        if (is_array($data) || $data instanceof \Illuminate\Support\Collection) {
            $response['data'] = $data;
            $response['total'] = is_countable($data) ? count($data) : 0;
            return response()->json($response, $code);
        }

        // ✅ لو نوع غير متوقع
        $response['data'] = $data;
        return response()->json($response, $code);
    }
}
/**
 * ❌ إرجاع استجابة خطأ منطقي أو تحقق
 */
if (!function_exists('api_error')) {
    function api_error(string $message = 'حدث خطأ ما', array $errors = [], int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}

/**
 * 💥 إرجاع استجابة في حالة Exception
 */
if (!function_exists('api_exception')) {
    function api_exception(Throwable $e, int $code = 500, string $message = null): JsonResponse
    {
        // التعامل مع أنواع محددة من الأخطاء
        if ($e instanceof ValidationException) {
            return response()->json([
                'status'  => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors'  => $e->errors(),
            ], 422);
        } elseif ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'status'  => false,
                'message' => "السجل غير موجود ($model)",
                'error'   => $e->getMessage(),
            ], 404);
        } elseif (
            $e instanceof \Illuminate\Auth\Access\AuthorizationException ||
            $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException ||
            $e instanceof \Spatie\Permission\Exceptions\UnauthorizedException
        ) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'ليس لديك صلاحية لإجراء هذه العملية.',
                'error'   => $e->getMessage(),
            ], 403);
        }

        // استخدام رسالة الـ Exception الفعلية كرسالة رئيسية للمستخدم
        $userMessage = $message ?? $e->getMessage() ?? 'حدث خطأ غير متوقع';

        // تجميع تفاصيل الخطأ
        $errorDetails = [
            'status'    => false,
            'message'   => $userMessage,
            'error'     => $e->getMessage(),
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => config('app.debug') ? explode("\n", $e->getTraceAsString()) : [],
        ];

        // تسجيل الخطأ
        Log::error('تفاصيل الخطأ:', $errorDetails);

        // إرجاع استجابة JSON
        return response()->json($errorDetails, $code);
    }
}

/**
 * 🛑 استجابة: لم يتم العثور على المورد
 */
if (!function_exists('api_not_found')) {
    function api_not_found(string $message = 'المورد غير موجود'): JsonResponse
    {
        return api_error($message, [], 404);
    }
}

/**
 * 🔐 استجابة: غير مصرح
 */
if (!function_exists('api_unauthorized')) {
    function api_unauthorized(string $message = 'غير مصرح بالدخول'): JsonResponse
    {
        return api_error($message, [], 401);
    }
}

/**
 * 🚫 استجابة: ممنوع الوصول
 */
if (!function_exists('api_forbidden')) {
    function api_forbidden(string $message = 'ممنوع الوصول لهذا المورد'): JsonResponse
    {
        return api_error($message, [], 403);
    }
}

/**
 * 📭 استجابة: لا يوجد بيانات
 */
if (!function_exists('api_no_content')) {
    function api_no_content(string $message = 'لا توجد بيانات'): JsonResponse
    {
        return api_error($message, [], 204);
    }
}
