<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profit\StoreProfitRequest; // ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ø³Ù… Ù…Ø¬Ù„Ø¯ Ø§Ù„Ø·Ù„Ø¨
use App\Http\Requests\Profit\UpdateProfitRequest; // ØªÙ… Ø¥Ø¶Ø§ÙØ© Ø·Ù„Ø¨ Ø§Ù„ØªØ­Ø¯ÙŠØ«
use App\Http\Resources\Profit\ProfitResource; // ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ø³Ù… Ù…Ø¬Ù„Ø¯ Ø§Ù„Ù…ÙˆØ±Ø¯
use App\Models\Profit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfitController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',   // Ù„Ù„ØªØ­Ù‚Ù‚ Ù…Ù† belongsToCurrentCompany
            'creator',   // Ù„Ù„ØªØ­Ù‚Ù‚ Ù…Ù† createdByCurrentUser/OrChildren
            // Ø£Ø¶Ù Ø£ÙŠ Ø¹Ù„Ø§Ù‚Ø§Øª Ø£Ø®Ø±Ù‰ Ø°Ø§Øª ØµÙ„Ø© Ù‡Ù†Ø§ØŒ Ù…Ø«Ù„ 'invoiceItem' Ø£Ùˆ 'user' Ø¥Ø°Ø§ ÙƒØ§Ù†Øª Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ù‡Ø§ Ù…Ø¨Ø§Ø´Ø±Ø©Ù‹
        ];
    }

    /**
     * @group 06. Ø§Ù„Ø¹Ù…Ù„ÙŠØ§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ø®Ø²ÙŠÙ†Ø©
     * 
     * Ø¹Ø±Ø¶ Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ø£Ø±Ø¨Ø§Ø­
     * 
     * @queryParam amount_from number Ø§Ù„Ù…Ø¨Ù„Øº Ù…Ù†. Example: 50
     * @queryParam amount_to number Ø§Ù„Ù…Ø¨Ù„Øº Ø¥Ù„Ù‰. Example: 500
     * @queryParam created_at_from date Ø§Ù„ØªØ§Ø±ÙŠØ® Ù…Ù†. Example: 2023-01-01
     * @queryParam per_page integer Ø¹Ø¯Ø¯ Ø§Ù„Ù†ØªØ§Ø¦Ø¬. Default: 15
     * 
     * @apiResourceCollection App\Http\Resources\Profit\ProfitResource
     * @apiResourceModel App\Models\Profit
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');
            }

            $query = Profit::query()->with($this->relations);
            $companyId = $authUser->active_company_id ?? null;

            // ØªØ·Ø¨ÙŠÙ‚ ÙÙ„ØªØ±Ø© Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // Ø§Ù„Ù…Ø³Ø¤ÙˆÙ„ Ø§Ù„Ø¹Ø§Ù… ÙŠØ±Ù‰ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­
            } elseif ($authUser->hasAnyPermission([perm_key('profits.view_all'), perm_key('admin.company')])) {
                // ÙŠØ±Ù‰ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_children'))) {
                // ÙŠØ±Ù‰ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø£Ùˆ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ† Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ† Ù„Ù‡ØŒ Ø¶Ù…Ù† Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_self'))) {
                // ÙŠØ±Ù‰ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙÙ‚Ø·ØŒ ÙˆÙ…Ø±ØªØ¨Ø·Ø© Ø¨Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ Ø¥Ø°Ù† Ù„Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­.');
            }

            // ÙÙ„Ø§ØªØ± Ø§Ù„Ø·Ù„Ø¨ Ø§Ù„Ø¥Ø¶Ø§ÙÙŠØ©
            if ($request->filled('company_id')) {
                // ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù„Ø¯ÙŠÙ‡ ØµÙ„Ø§Ø­ÙŠØ© Ø±Ø¤ÙŠØ© Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ù„Ø´Ø±ÙƒØ© Ø£Ø®Ø±Ù‰ Ø¥Ø°Ø§ ØªÙ… ØªØ­Ø¯ÙŠØ¯Ù‡Ø§
                if ($request->input('company_id') != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ Ø¥Ø°Ù† Ù„Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ù„Ø´Ø±ÙƒØ© Ø£Ø®Ø±Ù‰.');
                }
                $query->where('company_id', $request->input('company_id'));
            }
            if ($request->filled('amount_from')) {
                $query->where('amount', '>=', $request->input('amount_from'));
            }
            if ($request->filled('amount_to')) {
                $query->where('amount', '<=', $request->input('amount_to'));
            }
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // ØªØ­Ø¯ÙŠØ¯ Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù†Ø§ØµØ± ÙÙŠ Ø§Ù„ØµÙØ­Ø© ÙˆØ§Ù„ÙØ±Ø²
            $perPage = max(1, (int) $request->get('per_page', 15));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $profits = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($profits->isEmpty()) {
                return api_success([], 'Ù„Ù… ÙŠØªÙ… Ø§Ù„Ø¹Ø«ÙˆØ± Ø¹Ù„Ù‰ Ø£Ø±Ø¨Ø§Ø­.');
            } else {
                return api_success(ProfitResource::collection($profits), 'ØªÙ… Ø¬Ù„Ø¨ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø¨Ù†Ø¬Ø§Ø­.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. Ø§Ù„Ø¹Ù…Ù„ÙŠØ§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ø®Ø²ÙŠÙ†Ø©
     * 
     * ØªØ³Ø¬ÙŠÙ„ Ø±Ø¨Ø­ Ø¬Ø¯ÙŠØ¯
     * 
     * @bodyParam amount number required Ø§Ù„Ù…Ø¨Ù„Øº. Example: 150
     * @bodyParam description string required ÙˆØµÙ Ø§Ù„Ø±Ø¨Ø­. Example: Ø¹Ù…ÙˆÙ„Ø© Ù…Ø¨ÙŠØ¹Ø§Øª
     * @bodyParam company_id integer Ù…Ø¹Ø±Ù Ø§Ù„Ø´Ø±ÙƒØ© (Ù„Ù„Ù…Ø¯Ø±Ø§Ø¡). Example: 1
     */
    public function store(StoreProfitRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');
            }
            if (!$companyId) {
                return api_forbidden('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§Ù„Ø´Ø±ÙƒØ©.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('profits.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ Ø¥Ø°Ù† Ù„Ø¥Ù†Ø´Ø§Ø¡ Ø£Ø±Ø¨Ø§Ø­.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // Ø¥Ø°Ø§ ÙƒØ§Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… super_admin ÙˆÙŠØ­Ø¯Ø¯ company_idØŒ ÙŠØ³Ù…Ø­ Ø¨Ø°Ù„Ùƒ. ÙˆØ¥Ù„Ø§ØŒ Ø§Ø³ØªØ®Ø¯Ù… company_id Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù….
                $profitCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // Ø§Ù„ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù…ØµØ±Ø­ Ù„Ù‡ Ø¨Ø¥Ù†Ø´Ø§Ø¡ Ø±Ø¨Ø­ Ù„Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©
                if ($profitCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('ÙŠÙ…ÙƒÙ†Ùƒ ÙÙ‚Ø· Ø¥Ù†Ø´Ø§Ø¡ Ø£Ø±Ø¨Ø§Ø­ Ù„Ø´Ø±ÙƒØªÙƒ Ø§Ù„Ø­Ø§Ù„ÙŠØ© Ù…Ø§ Ù„Ù… ØªÙƒÙ† Ù…Ø³Ø¤ÙˆÙ„Ø§Ù‹ Ø¹Ø§Ù…Ù‹Ø§.');
                }
                $validatedData['company_id'] = $profitCompanyId;

                $profit = Profit::create($validatedData);
                $profit->load($this->relations);
                DB::commit();
                return api_success(new ProfitResource($profit), 'ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ø±Ø¨Ø­ Ø¨Ù†Ø¬Ø§Ø­.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('ÙØ´Ù„ Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø£Ø«Ù†Ø§Ø¡ ØªØ®Ø²ÙŠÙ† Ø§Ù„Ø±Ø¨Ø­.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø­ÙØ¸ Ø§Ù„Ø±Ø¨Ø­.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. Ø§Ù„Ø¹Ù…Ù„ÙŠØ§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ø®Ø²ÙŠÙ†Ø©
     * 
     * Ø¹Ø±Ø¶ ØªÙØ§ØµÙŠÙ„ Ø±Ø¨Ø­
     * 
     * @urlParam profit required Ù…Ø¹Ø±Ù Ø§Ù„Ø±Ø¨Ø­. Example: 1
     * 
     * @apiResource App\Http\Resources\Profit\ProfitResource
     * @apiResourceModel App\Models\Profit
     */
    public function show(Profit $profit): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');
            }
            if (!$companyId) {
                return api_forbidden('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§Ù„Ø´Ø±ÙƒØ©.');
            }

            $profit->load($this->relations);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('profits.view_all'), perm_key('admin.company')])) {
                $canView = $profit->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_children'))) {
                $canView = $profit->belongsToCurrentCompany() && $profit->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_self'))) {
                $canView = $profit->belongsToCurrentCompany() && $profit->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new ProfitResource($profit), 'ØªÙ… Ø§Ø³ØªØ±Ø¯Ø§Ø¯ Ø§Ù„Ø±Ø¨Ø­ Ø¨Ù†Ø¬Ø§Ø­.');
            }

            return api_forbidden('Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ Ø¥Ø°Ù† Ù„Ø¹Ø±Ø¶ Ù‡Ø°Ø§ Ø§Ù„Ø±Ø¨Ø­.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. Ø§Ù„Ø¹Ù…Ù„ÙŠØ§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ø®Ø²ÙŠÙ†Ø©
     * 
     * ØªØ­Ø¯ÙŠØ« Ø±Ø¨Ø­
     * 
     * @urlParam profit required Ù…Ø¹Ø±Ù Ø§Ù„Ø±Ø¨Ø­. Example: 1
     * @bodyParam amount number Ø§Ù„Ù…Ø¨Ù„Øº Ø§Ù„Ù…Ø­Ø¯Ø«. Example: 200
     */
    public function update(UpdateProfitRequest $request, Profit $profit): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');
            }
            if (!$companyId) {
                return api_forbidden('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§Ù„Ø´Ø±ÙƒØ©.');
            }

            $profit->load(['company', 'creator']); // ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¹Ù„Ø§Ù‚Ø§Øª Ù„Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('profits.update_all'), perm_key('admin.company')])) {
                $canUpdate = $profit->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.update_children'))) {
                $canUpdate = $profit->belongsToCurrentCompany() && $profit->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.update_self'))) {
                $canUpdate = $profit->belongsToCurrentCompany() && $profit->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ Ø¥Ø°Ù† Ù„ØªØ­Ø¯ÙŠØ« Ù‡Ø°Ø§ Ø§Ù„Ø±Ø¨Ø­.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // Ø§Ù„ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù…ØµØ±Ø­ Ù„Ù‡ Ø¨ØªØºÙŠÙŠØ± company_id Ø¥Ø°Ø§ ÙƒØ§Ù† Ø³ÙˆØ¨Ø± Ø£Ø¯Ù…Ù†
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $profit->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('Ù„Ø§ ÙŠÙ…ÙƒÙ†Ùƒ ØªØºÙŠÙŠØ± Ø´Ø±ÙƒØ© Ø§Ù„Ø±Ø¨Ø­ Ø¥Ù„Ø§ Ø¥Ø°Ø§ ÙƒÙ†Øª Ù…Ø¯ÙŠØ± Ø¹Ø§Ù….');
                }
                // Ø¥Ø°Ø§ Ù„Ù… ÙŠØªÙ… ØªØ­Ø¯ÙŠØ¯ company_id ÙÙŠ Ø§Ù„Ø·Ù„Ø¨ ÙˆÙ„ÙƒÙ† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø³ÙˆØ¨Ø± Ø£Ø¯Ù…Ù†ØŒ Ù„Ø§ ØªØºÙŠØ± company_id Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ø±Ø¨Ø­ Ø§Ù„Ø­Ø§Ù„ÙŠ
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $profit->update($validatedData);
                $profit->load($this->relations);
                DB::commit();
                return api_success(new ProfitResource($profit), 'ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø±Ø¨Ø­ Ø¨Ù†Ø¬Ø§Ø­.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('ÙØ´Ù„ Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø£Ø«Ù†Ø§Ø¡ ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø±Ø¨Ø­.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø±Ø¨Ø­.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. Ø§Ù„Ø¹Ù…Ù„ÙŠØ§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ø®Ø²ÙŠÙ†Ø©
     * 
     * Ø­Ø°Ù Ø±Ø¨Ø­
     * 
     * @urlParam profit required Ù…Ø¹Ø±Ù Ø§Ù„Ø±Ø¨Ø­. Example: 1
     */
    public function destroy(Profit $profit): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ù…ØµØ§Ø¯Ù‚Ø©.');
            }
            if (!$companyId) {
                return api_forbidden('ÙŠØªØ·Ù„Ø¨ Ø§Ù„Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§Ù„Ø´Ø±ÙƒØ©.');
            }

            $profit->load(['company', 'creator']); // ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¹Ù„Ø§Ù‚Ø§Øª Ù„Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('profits.delete_all'), perm_key('admin.company')])) {
                $canDelete = $profit->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.delete_children'))) {
                $canDelete = $profit->belongsToCurrentCompany() && $profit->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.delete_self'))) {
                $canDelete = $profit->belongsToCurrentCompany() && $profit->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ Ø¥Ø°Ù† Ù„Ø­Ø°Ù Ù‡Ø°Ø§ Ø§Ù„Ø±Ø¨Ø­.');
            }

            DB::beginTransaction();
            try {
                // Ø­ÙØ¸ Ù†Ø³Ø®Ø© Ù…Ù† Ø§Ù„Ø±Ø¨Ø­ Ù‚Ø¨Ù„ Ø­Ø°ÙÙ‡ Ù„Ø¥Ø±Ø¬Ø§Ø¹Ù‡Ø§ ÙÙŠ Ø§Ù„Ø§Ø³ØªØ¬Ø§Ø¨Ø©
                $deletedProfit = $profit->replicate();
                $deletedProfit->setRelations($profit->getRelations());

                $profit->update(['status' => 'reversed']);
                $profit->delete();
                DB::commit();
                return api_success(new ProfitResource($profit), 'ØªÙ… Ø­Ø°Ù Ø§Ù„Ø±Ø¨Ø­ Ø¨Ù†Ø¬Ø§Ø­.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø­Ø°Ù Ø§Ù„Ø±Ø¨Ø­.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

