<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreRevenueRequest;
use Modules\Accounting\Http\Requests\UpdateRevenueRequest;
use Modules\Accounting\Http\Resources\RevenueResource;
use Modules\Accounting\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Ù…ØªØ­ÙƒÙ… Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª (RevenueController) - Ù…ÙˆØ¯ÙŠÙˆÙ„ Ø§Ù„Ù…Ø­Ø§Ø³Ø¨Ø©
 */
class RevenueController extends Controller
{
    protected RevenueService $revenueService;

    public function __construct(RevenueService $revenueService)
    {
        $this->revenueService = $revenueService;
    }
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',
            'customer',
            'creator',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');

            $query = Revenue::query()->with($this->relations);

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            } elseif ($authUser->hasAnyPermission([perm_key('revenues.view_all'), perm_key('admin.company')])) {
                $query->where('company_id', $authUser->active_company_id);
            } else {
                $query->where('created_by', $authUser->id);
            }

            $perPage = max(1, (int) $request->get('per_page', 15));
            $revenues = $query->orderBy('id', 'desc')->paginate($perPage);

            return api_success(RevenueResource::collection($revenues), 'ØªÙ… Ø¬Ù„Ø¨ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª Ø¨Ù†Ø¬Ø§Ø­.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(StoreRevenueRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('revenues.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ Ø¥Ø°Ù† Ù„Ø¥Ù†Ø´Ø§Ø¡ Ø¥ÙŠØ±Ø§Ø¯Ø§Øª.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->active_company_id;

                if (empty($validatedData['wallet_id'])) {
                    $defaultBox = $authUser->getDefaultCashBoxForCompany($validatedData['company_id']);
                    if (!$defaultBox) {
                        throw new \Exception('Ù„Ø§ ØªÙˆØ¬Ø¯ Ø®Ø²Ù†Ø© Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù„Ø¥ØªÙ…Ø§Ù… Ø¹Ù…Ù„ÙŠØ© Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯.');
                    }
                    $validatedData['wallet_id'] = $defaultBox->id;
                }

                $revenue = $this->revenueService->createRevenue($validatedData);
                $revenue->load($this->relations);
                DB::commit();
                return api_success(new RevenueResource($revenue), 'ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯ Ø¨Ù†Ø¬Ø§Ø­.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function show(Revenue $revenue): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $revenue->company_id !== $authUser->active_company_id) {
                return api_forbidden();
            }

            return api_success(new RevenueResource($revenue->load($this->relations)), 'ØªÙ… Ø§Ø³ØªØ±Ø¯Ø§Ø¯ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯ Ø¨Ù†Ø¬Ø§Ø­.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function update(UpdateRevenueRequest $request, Revenue $revenue): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || ($revenue->company_id !== $authUser->active_company_id && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_forbidden();
            }

            DB::beginTransaction();
            try {
                $revenue->update($request->validated());
                DB::commit();
                return api_success(new RevenueResource($revenue->load($this->relations)), 'ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯ Ø¨Ù†Ø¬Ø§Ø­.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function destroy(Revenue $revenue): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || ($revenue->company_id !== $authUser->active_company_id && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_forbidden();
            }

            $this->revenueService->reverseRevenue($revenue, $authUser->id);
            return api_success(null, 'ØªÙ… Ø­Ø°Ù Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯ Ø¨Ù†Ø¬Ø§Ø­.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

