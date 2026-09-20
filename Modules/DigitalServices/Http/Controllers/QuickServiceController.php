<?php

namespace Modules\DigitalServices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\DigitalServices\Services\ServiceProcessor;

class QuickServiceController extends Controller
{
    public function __construct(protected ServiceProcessor $processor) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_definition_id' => 'required|integer|exists:service_definitions,id',
            'provider_account_id' => 'required|integer|exists:provider_accounts,id',
            'cash_box_id' => 'required|integer|exists:cash_boxes,id', // درج الكاشير
            'branch_id' => 'nullable|integer',
            'service_amount' => 'required|numeric|min:0',
            'network_fee' => 'required|numeric|min:0',
            'shop_commission' => 'required|numeric|min:0',
            'provider_amount' => 'required|numeric|min:0',
            'cash_amount' => 'required|numeric|min:0',
            'provider_reference_id' => 'nullable|string',
            'status' => 'nullable|string' // e.g. Awaiting External Confirmation
        ]);

        $transaction = $this->processor->process(
            $validated, 
            $request->user()->company_id, 
            $request->user()->id
        );

        return response()->json([
            'message' => 'تم تسجيل العملية بنجاح.', 
            'data' => $transaction
        ]);
    }
}
