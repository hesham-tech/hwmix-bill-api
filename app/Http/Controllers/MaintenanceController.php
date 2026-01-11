<?php

namespace App\Http\Controllers;

use App\Models\CompanyUser;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @hideFromApiDocs
 */
class MaintenanceController extends Controller
{
    /**
     * تصحيح السجلات المفقودة للخزن الافتراضية
     */
    public function fixMissingCashBoxes(): JsonResponse
    {
        $missingCount = 0;

        // 1. جلب جميع ارتباطات المستخدمين بالشركات
        $userCompanies = CompanyUser::with('company')->get(['user_id', 'company_id', 'created_by']);

        DB::beginTransaction();

        try {
            foreach ($userCompanies as $cu) {
                $exists = CashBox::where('user_id', $cu->user_id)
                    ->where('company_id', $cu->company_id)
                    ->where('is_default', 1)
                    ->exists();

                if (!$exists) {
                    $cashType = CashBoxType::where('name', 'نقدي')
                        ->where('company_id', $cu->company_id)
                        ->first();

                    if (!$cashType) {
                        Log::warning("MaintenanceController: Missing 'نقدي' type for company {$cu->company_id}");
                        continue;
                    }

                    $companyName = $cu->company ? $cu->company->name : 'غير محدد';

                    CashBox::create([
                        'name' => 'الخزنة النقدية',
                        'balance' => '0.00',
                        'cash_box_type_id' => $cashType->id,
                        'is_default' => 1,
                        'user_id' => $cu->user_id,
                        'created_by' => $cu->created_by ?? $cu->user_id,
                        'company_id' => $cu->company_id,
                        'description' => "تصحيح بيانات: تم إنشاؤها تلقائيًا للشركة: **{$companyName}**",
                        'account_number' => null,
                    ]);

                    $missingCount++;
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تمت عملية تصحيح السجلات القديمة بنجاح.',
                'boxes_created' => $missingCount,
                'note' => 'يرجى العلم أن هذا المسار مخفي من التوثيق ولأغراض الصيانة فقط.'
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API Fix CashBoxes Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'فشل التصحيح! حدث خطأ في قاعدة البيانات.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}
