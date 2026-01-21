<?php

namespace App\Http\Controllers;

use App\Models\FinancialLedger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\FinancialLedger\FinancialLedgerResource;

class FinancialLedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FinancialLedger::query()
            ->when($request->search, function ($q) use ($request) {
                return $q->where('description', 'like', "%{$request->search}%");
            })
            ->when($request->type, function ($q) use ($request) {
                return $q->where('source_type', $request->type);
            })
            ->when($request->date_from, function ($q) use ($request) {
                return $q->whereDate('entry_date', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                return $q->whereDate('entry_date', '<=', $request->date_to);
            });

        // تطبيق منطق الصلاحيات
        $user = auth()->user();
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            // المسؤول العام يرى كل شيء
        } elseif ($user->hasAnyPermission([perm_key('financial_ledger.view_all'), perm_key('admin.company')])) {
            $query->whereCompanyIsCurrent();
        } elseif ($user->hasPermissionTo(perm_key('financial_ledger.view_children'))) {
            $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
        } else {
            $query->whereCompanyIsCurrent()->whereCreatedByUser();
        }

        $entries = $query->latest('entry_date')->paginate($request->per_page ?? 15);

        return api_success(FinancialLedgerResource::collection($entries));
    }

    public function export(Request $request): JsonResponse
    {
        // تمثيلية للتصدير حالياً
        return api_success(null, 'سيتم إرسال ملف التصدير إلى بريدك الإلكتروني قريباً');
    }
}
