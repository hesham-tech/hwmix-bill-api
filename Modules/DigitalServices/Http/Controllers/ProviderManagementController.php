<?php

namespace Modules\DigitalServices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\DigitalServices\Services\ProviderAccountSetupService;

class ProviderManagementController extends Controller
{
    public function __construct(protected ProviderAccountSetupService $setupService) {}

    public function index(Request $request)
    {
        // Get all configured machines/wallets for this company
        $accounts = \Illuminate\Support\Facades\DB::table('provider_accounts')
            ->where('company_id', $request->user()->company_id)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'type', 'cash_box_id')
            ->get();
            
        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_provider_id' => 'required|integer|exists:service_providers,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:digital_wallet,payment_machine',
            'hwnix_cash_financial_account_id' => 'nullable|integer',
        ]);

        // Orchestrator takes care of creating ProviderAccount + CashBox + applying Commission Defaults
        $account = $this->setupService->setup(
            $validated, 
            $request->user()->company_id, 
            $request->user()->id
        );

        return response()->json([
            'message' => 'تم إعداد الماكينة/المحفظة بنجاح وربطها بالخزائن محاسبياً.', 
            'data' => $account
        ]);
    }
}
