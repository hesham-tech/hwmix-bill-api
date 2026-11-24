<?php

namespace App\Http\Controllers;

use App\Services\CustomerInstallmentCollectionService;
use Illuminate\Http\Request;

class CustomerInstallmentCollectionController extends Controller
{
    public function candidates(Request $request, CustomerInstallmentCollectionService $service)
    {
        $input = $request->validate(['customer_id' => 'required|integer|min:1']);
        return api_success($service->candidates($input['customer_id']));
    }

    public function preview(Request $request, CustomerInstallmentCollectionService $service)
    {
        $input = $request->validate([
            'customer_id' => 'required|integer|min:1', 'installment_ids' => 'required|array|min:1',
            'installment_ids.*' => 'required|integer|distinct',
            'amount' => 'required|numeric|gt:0|decimal:0,2|max:999999999.99',
            'allocations' => 'sometimes|array', 'allocations.*.installment_id' => 'required|integer|distinct',
            'allocations.*.amount' => 'required|numeric|min:0|decimal:0,2',
            'payment_method_id' => 'required|integer', 'cash_box_id' => 'required|integer',
            'paid_at' => 'required|date_format:Y-m-d', 'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);
        return api_success($service->issuePreview($input));
    }

    public function store(Request $request, CustomerInstallmentCollectionService $service)
    {
        $input = $request->validate(['token' => 'required|string', 'request_key' => 'required|uuid']);
        return api_success($service->commit($input['token'], $input['request_key']), 'تم تحصيل أقساط العميل بنجاح.');
    }
}
