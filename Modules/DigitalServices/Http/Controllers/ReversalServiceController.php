<?php

namespace Modules\DigitalServices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\DigitalServices\Models\ServiceTransaction;

class ReversalServiceController extends Controller
{
    /**
     * يعكس العملية (Refund/Reversal) بإنشاء قيد عكسي 
     * مع الالتزام بقاعدة عدم حذف أي قيد تاريخي (Immutable).
     */
    public function reverse(Request $request, $transactionId)
    {
        $validated = $request->validate([
            'reversal_reason' => 'required|string|max:500'
        ]);

        $original = ServiceTransaction::where('company_id', $request->user()->company_id)
                                      ->findOrFail($transactionId);

        if ($original->status === 'Reversed') {
            return response()->json(['message' => 'العملية معكوسة مسبقاً.'], 400);
        }

        // TODO: Orchestrate generating a negative mirror transaction 
        // and posting a reverse double-entry through FinancialEngine.
        
        $original->update([
            'status' => 'Reversed',
            'reversal_reason' => $validated['reversal_reason'],
            'reversed_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'تم عكس العملية بنجاح.']);
    }
}
