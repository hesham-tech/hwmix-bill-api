<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sales\Models\Subscription;
use Modules\Sales\Services\SubscriptionRenewalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class SubscriptionRenewalController extends Controller
{
    protected $renewalService;

    public function __construct(SubscriptionRenewalService $renewalService)
    {
        $this->renewalService = $renewalService;
    }

    public function renew(Request $request, $id): JsonResponse
    {
        $subscription = Subscription::where('company_id', auth()->user()->active_company_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_box_id' => 'nullable|exists:cash_boxes,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        try {
            $result = $this->renewalService->renew($subscription, $request->all());
            return api_success($result, 'تم تجديد الاشتراك بنجاح');
        } catch (\Exception $e) {
            return api_error('فشل تجديد الاشتراك', ['error' => $e->getMessage()], 500);
        }
    }

    public function history($id): JsonResponse
    {
        $subscription = Subscription::where('company_id', auth()->user()->active_company_id)->findOrFail($id);
        $history = $subscription->payments()->with(['paymentMethod', 'cashBox', 'creator'])->latest()->paginate(10);
        return response()->json($history);
    }
}
