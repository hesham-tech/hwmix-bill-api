<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sales\Http\Requests\StoreSubscriptionRequest;
use Modules\Sales\Http\Requests\UpdateSubscriptionRequest;
use Modules\Sales\Http\Resources\SubscriptionResource;
use Modules\Sales\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubscriptionController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = ['creator', 'company', 'user', 'plan', 'service'];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = Subscription::query()->with($this->relations);

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            } elseif ($authUser->hasAnyPermission([perm_key('subscriptions.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الاشتراكات.');
            }

            if ($request->filled('user_id')) $query->where('user_id', $request->input('user_id'));
            if ($request->filled('status')) $query->where('status', $request->input('status'));

            $perPage = max(1, (int) $request->get('per_page', 20));
            $subscriptions = $query->orderBy($request->input('sort_by', 'id'), $request->input('sort_order', 'desc'))->paginate($perPage);

            return $subscriptions->isEmpty() 
                ? api_success([], 'لم يتم العثور على اشتراكات.')
                : api_success(SubscriptionResource::collection($subscriptions), 'تم جلب الاشتراكات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة أو اختيار شركة نشطة.');

            if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('subscriptions.create'), perm_key('admin.company')])) {
                return api_forbidden('ليس لديك إذن لإنشاء اشتراكات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $companyId;

                if (empty($validatedData['next_billing_date']) && !empty($validatedData['starts_at'])) {
                    $startDate = \Carbon\Carbon::parse($validatedData['starts_at']);
                    $cycle = $validatedData['billing_cycle'] ?? 'monthly';
                    $validatedData['next_billing_date'] = match ($cycle) {
                        'daily' => $startDate->addDay(),
                        'weekly' => $startDate->addWeek(),
                        'yearly' => $startDate->addYear(),
                        default => $startDate->addMonth(),
                    };
                }

                $subscription = Subscription::create($validatedData);
                $subscription->load($this->relations);
                DB::commit();
                return api_success(new SubscriptionResource($subscription), 'تم إنشاء الاشتراك بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $subscription = Subscription::with($this->relations)->findOrFail($id);
            $canView = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                      ($authUser->hasAnyPermission([perm_key('subscriptions.view_all'), perm_key('admin.company')]) && $subscription->belongsToCurrentCompany()) ||
                      ($authUser->hasPermissionTo(perm_key('subscriptions.view_children')) && $subscription->belongsToCurrentCompany() && $subscription->createdByUserOrChildren()) ||
                      ($authUser->hasPermissionTo(perm_key('subscriptions.view_self')) && $subscription->belongsToCurrentCompany() && $subscription->createdByCurrentUser());

            return $canView 
                ? api_success(new SubscriptionResource($subscription), 'تم استرداد الاشتراك بنجاح.')
                : api_forbidden('ليس لديك إذن لعرض هذا الاشتراك.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function update(UpdateSubscriptionRequest $request, string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $subscription = Subscription::findOrFail($id);
            $canUpdate = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('subscriptions.update_all'), perm_key('admin.company')]) && $subscription->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('subscriptions.update_children')) && $subscription->belongsToCurrentCompany() && $subscription->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('subscriptions.update_self')) && $subscription->belongsToCurrentCompany() && $subscription->createdByCurrentUser());

            if (!$canUpdate) return api_forbidden('ليس لديك إذن لتحديث هذا الاشتراك.');

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                $subscription->update($validatedData);
                $subscription->load($this->relations);
                DB::commit();
                return api_success(new SubscriptionResource($subscription), 'تم تحديث الاشتراك بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $subscription = Subscription::findOrFail($id);
            $canDelete = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('subscriptions.delete_all'), perm_key('admin.company')]) && $subscription->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('subscriptions.delete_children')) && $subscription->belongsToCurrentCompany() && $subscription->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('subscriptions.delete_self')) && $subscription->belongsToCurrentCompany() && $subscription->createdByCurrentUser());

            if (!$canDelete) return api_forbidden('ليس لديك إذن لحذف هذا الاشتراك.');

            DB::beginTransaction();
            try {
                $deletedSubscription = $subscription->replicate();
                $subscription->delete();
                DB::commit();
                return api_success(new SubscriptionResource($deletedSubscription), 'تم حذف الاشتراك بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
