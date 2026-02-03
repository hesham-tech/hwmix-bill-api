<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionRenewalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionRenewalController extends Controller
{
    protected $renewalService;

    public function __construct(SubscriptionRenewalService $renewalService)
    {
        $this->renewalService = $renewalService;
    }

    /**
     * Renew subscription
     */
    public function renew(Request $request, $id)
    {
        $subscription = Subscription::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_box_id' => 'nullable|exists:cash_boxes,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->renewalService->renew($subscription, $request->all());

            return response()->json([
                'message' => 'تم تجديد الاشتراك بنجاح',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل تجديد الاشتراك',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription payment history
     */
    public function history($id)
    {
        $subscription = Subscription::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $history = $subscription->payments()
            ->with(['paymentMethod', 'cashBox', 'creator'])
            ->latest()
            ->paginate(10);

        return response()->json($history);
    }
}
