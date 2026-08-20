<?php

namespace App\Models;

use Exception;
use App\Traits\Scopes;
use App\Traits\HasImages;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use App\Services\CashBoxService;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasBusinessCapabilities;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Traits\Translations\Translatable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\UserObserver;
// ÙŠØ¬Ø¨ Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù†Ù…Ø§Ø°Ø¬ (Models) Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…Ø© Ø¯Ø§Ø®Ù„ Ø§Ù„ÙƒÙˆØ¯:
use Modules\Accounting\Models\CashBox;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use Modules\Accounting\Models\Transaction;
use App\Models\Payment;
use App\Models\Translation; // ØªÙ… Ø§Ø³ØªØ®Ø¯Ø§Ù…Ù‡ ÙÙŠ Ø¯Ø§Ù„Ø© trans
use App\Models\RoleCompany; // ØªÙ… Ø§Ø³ØªØ®Ø¯Ø§Ù…Ù‡ ÙÙŠ Ø¯Ø§Ù„Ø© createdRoles


/**
 *   ÙƒÙ„Ø§Ø³ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù„Ù„Ù†Ø¸Ø§Ù… ÙˆÙŠÙ…Ø«Ù„ Ø§Ù„Ø­Ø³Ø§Ø¨ Ø§Ù„Ù…ÙˆØ­Ø¯ Ù„Ù„Ù‡ÙˆÙŠØ© Ø§Ù„ØµØ§Ù„Ø­Ø© Ù„Ù„Ø¹Ù…Ù„Ø§Ø¡ØŒ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†ØŒ ÙˆØ§Ù„Ù…ÙˆØ¸ÙÙŠÙ†.
 */
/**
 * @method void deposit(float|int $amount)
 */
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Translatable, HasApiTokens, Filterable, Scopes, LogsActivity, HasImages, \App\Traits\FilterableByCompany, \App\Traits\SmartSearch, HasBusinessCapabilities;
    use HasRoles, HasPermissions {
        HasPermissions::hasPermissionTo insteadof HasRoles;
        HasPermissions::hasPermissionTo as traitHasPermissionTo;
    }



    /**
     * Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„ØªÙŠ Ù„Ø§ ØªØ®Ø¶Ø¹ Ù„Ù€ Mass Assignment Protection.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„ØªÙŠ ÙŠØ¬Ø¨ Ø¥Ø®ÙØ§Ø¤Ù‡Ø§ Ø¹Ù†Ø¯ Ø§Ù„ØªØ­ÙˆÙŠÙ„ Ø¥Ù„Ù‰ Ù…ØµÙÙˆÙØ©/JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['avatar_url', 'name', 'is_default_cash_customer'];

    /**
     * ØªØ¹Ø±ÙŠÙ Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ù„Ù„Ù…Ø­ÙˆÙ„Ø§Øª (Casts).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    /**
     * Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª Ø§Ù„ØªÙŠ ÙŠØªÙ… ØªÙ†ÙÙŠØ°Ù‡Ø§ Ø¨Ø¹Ø¯ ØªÙ…Ù‡ÙŠØ¯ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬ (Ù…Ø«Ù„ Ø¥Ù†Ø´Ø§Ø¡ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ Ø§ÙØªØ±Ø§Ø¶ÙŠ Ø¹Ù†Ø¯ Ø¥Ù†Ø´Ø§Ø¡ Ù…Ø³ØªØ®Ø¯Ù… Ø¬Ø¯ÙŠØ¯).
     */
    protected static function booted(): void
    {
        /**
         * @see \App\Observers\UserObserver
         * ÙŠØªÙ… Ù…Ø¹Ø§Ù„Ø¬Ø© Ù…Ø²Ø§Ù…Ù†Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª (Sync) ÙˆØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù†Ø´Ø§Ø·Ø§Øª (ActivityLog) Ø¹Ø¨Ø± Ø§Ù„Ù…Ø±Ø§Ù‚Ø¨ (Observer)
         */

        static::created(function ($user) {
            // [ØªÙ…Øª Ø§Ù„Ø¥Ø²Ø§Ù„Ø©]: ÙŠØ¹ØªÙ…Ø¯ Ø§Ù„Ù†Ø¸Ø§Ù… Ø§Ù„Ø¢Ù† Ø¹Ù„Ù‰ CompanyUserObserver Ù„Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ø®Ø²Ù†Ø© Ø¹Ù†Ø¯ Ø§Ù„Ø±Ø¨Ø·
        });
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© MorphMany Ù„Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØªØ±Ø¬Ù…Ø§Øª Ø§Ù„Ù†Ù…ÙˆØ°Ø¬.
     */
    public function trans()
    {
        return $this->morphMany(Translation::class, 'model');
    }


    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ù„Ø´Ø±ÙƒØ© Ù…Ø­Ø¯Ø¯Ø© Ø£Ùˆ Ù„Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø© Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ù…ÙˆØ«Ù‚.
     *
     * @param int|null $companyId
     * @return CashBox|null
     */
    // ÙƒØ§Ø´ Ø¯Ø§Ø®Ù„ÙŠ Ù„ØªØ¬Ù†Ø¨ ØªÙƒØ±Ø§Ø± Ø§Ù„Ø§Ø³ØªØ¹Ù„Ø§Ù…Ø§Øª Ø¹Ù† Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©
    protected array $defaultCashBoxCache = [];

    public function getDefaultCashBoxForCompany($companyId = null, ?int $branchId = null)
    {
        return app(\App\Services\DefaultCashBoxResolver::class)->resolve($this, $companyId, $branchId);
    }

    public function canAccessCashBox($cashBox): bool
    {
        return app(\App\Services\CashBoxAccessService::class)->canAccess($this, $cashBox);
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©.
     * @deprecated ØªÙ… Ù†Ù‚Ù„ Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ø¥Ù„Ù‰ Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¨Ø§Ù„ÙØ±Ø¹ branch_user
     */
    public function defaultCashBox()
    {
        return $this->belongsTo(CashBox::class, 'default_cash_box_id');
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø³Ø¬Ù„ Ø§Ù„Ø¹Ø¶ÙˆÙŠØ© ÙˆØ§Ù„Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙÙŠ ÙØ±Ø¹ Ù…Ø¹ÙŠÙ†
     */
    public function branchMembership(?int $branchId = null): ?\App\Models\BranchUser
    {
        $branchId = $branchId ?? $this->branch_id;
        if (!$branchId) {
            return null;
        }
        return \App\Models\BranchUser::where('user_id', $this->id)
            ->where('branch_id', $branchId)
            ->first();
    }

    /**
     * Ù†Ø·Ø§Ù‚ Ø§Ø³ØªØ¹Ù„Ø§Ù… (Scope) Ù„ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ù…Ø¹ Ø®Ø²Ù†ØªÙ‡Ù… Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ù„Ø´Ø±ÙƒØ© Ù…Ø¹ÙŠÙ†Ø©.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $companyId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDefaultCashBox($query, $companyId = null)
    {
        $companyId = $companyId ?? app(\App\Services\CurrentCompanyResolver::class)->resolve() ?? null;

        return $query->with([
            'cashBoxes' => function ($q) use ($companyId) {
                $q->where('is_default', true);
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            }
        ]);
    }

    /**
     * Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø© Ø­Ø§Ù„ÙŠØ§Ù‹ Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'active_company_id');
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© BelongsToMany Ø¨ÙŠÙ† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙˆØ§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„ØªÙŠ ÙŠØ¹Ù…Ù„ Ø¨Ù‡Ø§.
     */
    public function companies(): BelongsToMany
    {
        return $this
            ->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id')
            ->using(CompanyUser::class)
            ->withTimestamps()
            ->withPivot([
                'nickname_in_company',
                'full_name_in_company',
                'position_in_company',
                'customer_type_in_company',
                'status',
                'created_by'
            ]);
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¬Ù…ÙŠØ¹ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙÙŠ Ø¬Ø¯ÙˆÙ„ company_user.
     */
    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'user_id');
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ø¹Ù„Ø§Ù‚Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙÙŠ Ø³ÙŠØ§Ù‚ Ø§Ù„Ø´Ø±ÙƒØ§Øª
     */
    public function businessRelations(): HasMany
    {
        return $this->hasMany(\Modules\Companies\Models\BusinessRelation::class, 'user_id');
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasOne Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø³Ø¬Ù„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ ÙÙŠ Ø¬Ø¯ÙˆÙ„ company_user Ù„Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©.
     */
    public function activeCompanyUser(): HasOne
    {
        $activeCompanyId = app(\App\Services\CurrentCompanyResolver::class)->resolve();

        return $this->hasOne(CompanyUser::class, 'user_id')
            ->where('company_id', $activeCompanyId);
    }



    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¬Ù…ÙŠØ¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ (Cash Boxes) Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function cashBoxes(): HasMany
    {
        return $this->hasMany(CashBox::class, 'user_id')->withoutGlobalScopes();
    }

    /**
     * Groups this user belongs to.
     */
    public function taskGroups(): BelongsToMany
    {
        return $this->belongsToMany(TaskGroup::class, 'task_group_user');
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¶Ù…Ù† Ø´Ø±ÙƒØ© Ù…Ø¹ÙŠÙ†Ø©.
     *
     * @param int|null $companyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCashBoxesForCompany($companyId = null)
    {
        $companyId = $companyId ?? app(\App\Services\CurrentCompanyResolver::class)->resolve() ?? null;

        if (!$companyId) {
            return collect();
        }

        return $this->cashBoxes()->where('company_id', $companyId)->get();
    }


    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø±ØµÙŠØ¯ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ÙŠØ© Ù…Ø­Ø¯Ø¯ Ø£Ùˆ ØµÙ†Ø¯ÙˆÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠ Ù„Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©.
     *
     * @param int|null $id Ù…Ø¹Ø±Ù ØµÙ†Ø¯ÙˆÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ©.
     * @return float
     */
    public function balanceBox($id = null): float
    {
        $cashBox = null;
        if ($id) {
            $cashBox = $this->cashBoxes()->where('id', $id)->first();
        } else {
            // Ø¬Ù„Ø¨ Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ù„Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ© (Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ Ø¬Ù„Ø³Ø© Ø§Ù„Ø¹Ù…Ù„ Ø£Ùˆ Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…)
            $cashBox = $this->getDefaultCashBoxForCompany();
        }
        return $cashBox ? (float) $cashBox->balance : 0.0;
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙÙŠ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø© (Ø§Ø³ØªØ¯Ø¹Ø§Ø¡ Ù„Ù€ getCashBoxesForCompany).
     */

    /**
     * Ø¹Ù„Ø§Ù‚Ø© Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… (Ø¹Ù„Ø§Ù‚Ø© HasManyThrough Ù…Ø¹ Ø¬Ø¯ÙˆÙ„ RoleCompany).
     */
    public function createdRoles()
    {
        return $this->hasManyThrough(
            Role::class,
            RoleCompany::class,
            'created_by',
            'id',
            'id',
            'role_id'
        );
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ù‡Ø°Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… (Ø§Ù„Ø¹Ù…ÙŠÙ„).
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'user_id');
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ù‡Ø°Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function createdInstallments(): HasMany
    {
        return $this->hasMany(Installment::class, 'created_by');
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø£Ø¯ÙˆØ§Ø± Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø¹ Ù‚Ø§Ø¦Ù…Ø© Ø£Ø°ÙˆÙ†Ø§Øª ÙƒÙ„ Ø¯ÙˆØ±.
     */
    public function getRolesWithPermissions()
    {
        return $this->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                }),
            ];
        });
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© BelongsTo Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø°ÙŠ Ø£Ù†Ø´Ø£ Ù‡Ø°Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„ØªÙŠ Ù‚Ø§Ù… Ø¨Ù‡Ø§ Ù‡Ø°Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }


    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¬Ù…ÙŠØ¹ ÙØ±ÙˆØ¹ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù…Ø±ØªØ¨Ø· Ø¨Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
     */
    public function companyBranches(): HasMany
    {
        return $this->hasMany(\Modules\Companies\Models\Branch::class, 'company_id', 'active_company_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Companies\Models\Branch::class, 'branch_user', 'user_id', 'branch_id')
            ->using(\App\Models\BranchUser::class)
            ->withPivot('default_cash_box_id', 'default_warehouse_id')
            ->withTimestamps();
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¬Ù…ÙŠØ¹ Ù…Ø¹Ø±ÙØ§Øª Ø§Ù„ÙØ±ÙˆØ¹ Ø§Ù„Ù…Ø³Ù…ÙˆØ­ Ù„Ù„Ù…ÙˆØ¸Ù Ø±Ø¤ÙŠØªÙ‡Ø§
     */
    public function getAllowedBranchIds(): array
    {
        $branchIds = $this->branches()->pluck('branches.id')->toArray();
        if ($this->branch_id && !in_array($this->branch_id, $branchIds)) {
            $branchIds[] = $this->branch_id; // Ø¥Ø¶Ø§ÙØ© Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠ Ø¥Ù† Ù„Ù… ÙŠÙƒÙ† ÙÙŠ Ø§Ù„Ø¬Ø¯ÙˆÙ„ Ø§Ù„ÙˆØ³ÙŠØ·
        }
        return $branchIds;
    }

    /**
     * Ø¹Ù„Ø§Ù‚Ø© HasMany Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø®Ø·Ø· Ø§Ù„ØªÙ‚Ø³ÙŠØ· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    /**
     * Ø¥Ø±Ø¬Ø§Ø¹ Ø¬Ù…ÙŠØ¹ Ù…Ø¹Ø±ÙØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ† Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ Ø¨Ø´ÙƒÙ„ ØªØ³Ù„Ø³Ù„ÙŠ Ø¯Ø§Ø®Ù„ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©.
     *
     * @return array
     */
    public function getDescendantUserIds(): array
    {
        // ÙŠØªØ·Ù„Ø¨ Ø§Ø³ØªÙŠØ±Ø§Ø¯ CompanyUser
        $companyId = app(\App\Services\CurrentCompanyResolver::class)->resolve() ?? null;

        if (is_null($companyId)) {
            return [];
        }

        $descendants = [];
        $stack = [$this->id];

        while (!empty($stack)) {
            $parentId = array_pop($stack);

            $children = CompanyUser::where('created_by', $parentId)
                ->where('company_id', $companyId)
                ->pluck('user_id')
                ->toArray();

            foreach ($children as $childUserId) {
                if (!in_array($childUserId, $descendants)) {
                    $descendants[] = $childUserId;
                    $stack[] = $childUserId;
                }
            }
        }

        if (($key = array_search($this->id, $descendants)) !== false) {
            unset($descendants[$key]);
        }
        return array_values($descendants);
    }



    /**
     * Ø¥Ø±Ø¬Ø§Ø¹ Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„Ù…Ø±Ø¦ÙŠØ© Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ ØµÙ„Ø§Ø­ÙŠØ§ØªÙ‡ (Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø´Ø±ÙƒØ§Øª Ù„Ù„Ø³ÙˆØ¨Ø± Ø£Ø¯Ù…Ù† Ø£Ùˆ Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„Ù…Ø±ØªØ¨Ø· Ø¨Ù‡Ø§).
     */
    public function getVisibleCompaniesForUser()
    {
        // ÙŠØªØ·Ù„Ø¨ Ø§Ø³ØªÙŠØ±Ø§Ø¯ Company
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return Company::all();
        }
        // Ø§Ø³ØªØ®Ø¯Ø§Ù… withoutGlobalScopes Ù„Ø±Ø¤ÙŠØ© Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„Ù…Ø±ØªØ¨Ø· Ø¨Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
        return $this->companies()->withoutGlobalScopes()->get();
    }

    /**
     * ÙŠØªØ­Ù‚Ù‚ Ù…Ù…Ø§ Ø¥Ø°Ø§ ÙƒØ§Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… (ÙƒØ¹Ù…ÙŠÙ„/Ù…ÙˆØ¸Ù) Ù„Ø¯ÙŠÙ‡ Ø£ÙŠ Ø³Ø¬Ù„Ø§Øª Ø­Ø±ÙƒÙŠØ©/Ù…Ø§Ù„ÙŠØ© Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.
     * @param int $companyId Ù…Ø¹Ø±Ù Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©
     * @return bool
     */
    /**
     * ÙŠØªØ­Ù‚Ù‚ Ù…Ù…Ø§ Ø¥Ø°Ø§ ÙƒØ§Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… (ÙƒØ¹Ù…ÙŠÙ„/Ù…ÙˆØ¸Ù) Ù„Ø¯ÙŠÙ‡ Ø£ÙŠ Ø³Ø¬Ù„Ø§Øª Ø­Ø±ÙƒÙŠØ©/Ù…Ø§Ù„ÙŠØ© Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.
     *
     * @param int $companyId Ù…Ø¹Ø±Ù Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©
     * @return array|null Ù…ØµÙÙˆÙØ© ØªØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ø³Ø¨Ø¨ Ø§Ù„Ù…Ù†Ø¹ (Ø§Ù„Ø±Ø³Ø§Ù„Ø©)ØŒ Ø£Ùˆ null Ø¥Ø°Ø§ ÙƒØ§Ù† Ø§Ù„Ø­Ø°Ù Ø¢Ù…Ù†Ù‹Ø§.
     */
    public function hasActiveTransactionsInCompany(int $companyId): ?array
    {
        // 1. ÙØ­Øµ Ø§Ù„ÙÙˆØ§ØªÙŠØ± (Invoices)
        if ($this->invoices()->where('company_id', $companyId)->exists()) {
            return ['message' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† ÙØµÙ„ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù„ÙˆØ¬ÙˆØ¯ ÙÙˆØ§ØªÙŠØ±  Ù…Ø³Ø¬Ù„Ø© Ø¨Ø§Ø³Ù…Ù‡ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©.'];
        }

        // 2. ÙØ­Øµ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© (Transactions)
        if ($this->transactions()->where('company_id', $companyId)->exists()) {
            return ['message' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† ÙØµÙ„ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù„ÙˆØ¬ÙˆØ¯ Ø³Ø¬Ù„Ø§Øª Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ù…Ø§Ù„ÙŠØ© Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ù‡ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©.'];
        }

        // 3. ÙØ­Øµ Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª (Payments)
        if ($this->payments()->where('company_id', $companyId)->exists()) {
            return ['message' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† ÙØµÙ„ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù„ÙˆØ¬ÙˆØ¯ Ø³Ø¬Ù„Ø§Øª Ù…Ø¯ÙÙˆØ¹Ø§Øª  Ù‚Ø§Ù… Ø¨Ù‡Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©.'];
        }

        // 4. ÙØ­Øµ Ø§Ù„Ø£Ù‚Ø³Ø§Ø· (Installments)
        if ($this->installments()->where('company_id', $companyId)->exists()) {
            return ['message' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† ÙØµÙ„ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù„ÙˆØ¬ÙˆØ¯ Ø£Ù‚Ø³Ø§Ø·  Ù…Ø³ØªØ­Ù‚Ø© Ø£Ùˆ Ù…Ø¯ÙÙˆØ¹Ø© Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ù‡ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©.'];
        }

        // 5. ÙØ­Øµ Ø®Ø·Ø· Ø§Ù„ØªÙ‚Ø³ÙŠØ· (Installment Plans)
        if ($this->installmentPlans()->where('company_id', $companyId)->exists()) {
            return ['message' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† ÙØµÙ„ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù„ÙˆØ¬ÙˆØ¯ Ø®Ø·Ø· ØªÙ‚Ø³ÙŠØ· Ù…Ø³Ø¬Ù„Ø© Ø¨Ø§Ø³Ù…Ù‡ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©.'];
        }

        // 6. ÙØ­Øµ Ø±ØµÙŠØ¯ Ø§Ù„Ø®Ø²Ù†Ø©: Ø¥Ø°Ø§ ÙƒØ§Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙŠÙ…ØªÙ„Ùƒ Ø®Ø²Ù†Ø© ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ© ÙˆØ±ØµÙŠØ¯Ù‡Ø§ Ù„ÙŠØ³ ØµÙØ±Ù‹Ø§
        if ($this->cashBoxes()->where('company_id', $companyId)->where('balance', '!=', 0)->exists()) {
            return ['message' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† ÙØµÙ„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù„ÙˆØ¬ÙˆØ¯ Ø±ØµÙŠØ¯ Ù…ØªØ¨Ù‚ÙŠ ØºÙŠØ± ØµÙØ±ÙŠ ÙÙŠ Ø®Ø²Ù†ØªÙ‡ Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ù„Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©.'];
        }

        // Ø¥Ø°Ø§ Ù„Ù… ÙŠØªÙ… Ø§Ù„Ø¹Ø«ÙˆØ± Ø¹Ù„Ù‰ Ø£ÙŠ Ø³Ø¬Ù„Ø§Øª Ø­Ø±ÙƒÙŠØ©/Ù…Ø§Ù„ÙŠØ©ØŒ ÙŠÙƒÙˆÙ† Ø§Ù„Ø­Ø°Ù Ø¢Ù…Ù†Ù‹Ø§
        return null;
    }



    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ({$this->nickname})";
    }

    /**
     * Get the user's avatar URL.
     */
    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute()
    {
        return $this->image?->url ? asset($this->image->url) : null;
    }

    /**
     * Override hasPermissionTo to handle 'admin.super' globally.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $superAdminKey = perm_key('admin.super');

        // Check for super admin permission in any context (team-blind)
        // This is necessary because super admin should have global access
        // even if the permission is seeded within a specific company.
        if ($permission === $superAdminKey || (is_object($permission) && $permission->name === $superAdminKey)) {
            static $isSuperAdmin = [];
            if (app()->runningUnitTests()) {
                $isSuperAdmin = [];
            }
            if (!isset($isSuperAdmin[$this->id])) {
                // Check direct permissions (team-blind)
                $hasDirect = \DB::table('model_has_permissions')
                    ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('model_id', $this->id)
                    ->where('model_type', get_class($this))
                    ->where('permissions.name', $superAdminKey)
                    ->exists();

                if ($hasDirect) {
                    $isSuperAdmin[$this->id] = true;
                } else {
                    // Check roles (team-blind)
                    $isSuperAdmin[$this->id] = \DB::table('model_has_roles')
                        ->join('role_has_permissions', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
                        ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                        ->where('model_id', $this->id)
                        ->where('model_type', get_class($this))
                        ->where('permissions.name', $superAdminKey)
                        ->exists();
                }
            }
            return $isSuperAdmin[$this->id];
        }

        return $this->traitHasPermissionTo($permission, $guardName);
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø§Ø³Ù… Ø§Ù„Ù…ÙØ¶Ù„ Ù„Ù„Ù‡ÙˆÙŠØ© Ø§Ù„Ø¹Ø§Ù„Ù…ÙŠØ© (Ø§Ù„Ø¹Ø§Ù„Ù…ÙŠ ÙÙ‚Ø·)
     */
    public function getNameAttribute()
    {
        if (!empty($this->nickname)) {
            return $this->nickname;
        }

        if (!empty($this->full_name)) {
            return $this->full_name;
        }

        if (!empty($this->username)) {
            return $this->username;
        }

        return 'Ø¹Ù…ÙŠÙ„ ØºÙŠØ± Ù…Ø¹Ø±ÙˆÙ';
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ù„Ù‚Ø¨ Ù„Ù„Ù‡ÙˆÙŠØ© Ø§Ù„Ø¹Ø§Ù„Ù…ÙŠØ© (Ø§Ù„Ø¹Ø§Ù„Ù…ÙŠ ÙÙ‚Ø·)
     */
    public function getNicknameAttribute($value)
    {
        return $value;
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø§Ø³Ù… Ø§Ù„ÙƒØ§Ù…Ù„ Ù„Ù„Ù‡ÙˆÙŠØ© Ø§Ù„Ø¹Ø§Ù„Ù…ÙŠØ© (Ø§Ù„Ø¹Ø§Ù„Ù…ÙŠ ÙÙ‚Ø·)
     */
    public function getFullNameAttribute($value)
    {
        return $value;
    }

    /**
     * Boot the trait to apply global scope.
     * ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ù†Ø·Ù‚ Ù„ÙŠØ´Ù…Ù„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ø§Ù„Ù…Ø±ØªØ¨Ø·ÙŠÙ† Ø¨Ø§Ù„Ø´Ø±ÙƒØ© Ø¹Ø¨Ø± Ø¬Ø¯ÙˆÙ„ company_user
     */
    public static function bootFilterableByCompany()
    {
        static::addGlobalScope('company_filter', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $user = Auth::user();
            
            // Skip applying the global scope during Sanctum authentication or guest requests
            if (!$user) {
                return;
            }

            $companyId = app(\App\Services\CurrentCompanyResolver::class)->resolve();

            if ($companyId && !$user->hasPermissionTo(perm_key('admin.super'))) {
                $builder->where(function ($query) use ($companyId) {
                    // 1. Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙŠÙ†ØªÙ…ÙŠ Ù…Ø¨Ø§Ø´Ø±Ø© Ù„Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ©
                    $query->where('users.active_company_id', $companyId)
                        // 2. Ø£Ùˆ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø±ØªØ¨Ø· Ø¨Ù‡Ø°Ù‡ Ø§Ù„Ø´Ø±ÙƒØ© Ø¹Ø¨Ø± Ø§Ù„Ø¬Ø¯ÙˆÙ„ Ø§Ù„ÙˆØ³ÙŠØ·
                        ->orWhereExists(function ($subQuery) use ($companyId) {
                            $subQuery->select(\DB::raw(1))
                                ->from('company_user')
                                ->whereColumn('company_user.user_id', 'users.id')
                                ->where('company_user.company_id', $companyId);
                        });
                });
            } elseif (!$user->hasPermissionTo(perm_key('admin.super'))) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Resolve the route binding for the model.
     *   ØªØ¬Ø§ÙˆØ² Ø§Ù„ÙÙ„ØªØ±Ø© Ø¨Ø§Ù„Ø´Ø±ÙƒØ© Ø¹Ù†Ø¯ Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¹Ø¨Ø± Ø§Ù„Ø±ÙˆØ§Ø¨Ø· Ù„ØªÙ…ÙƒÙŠÙ† Ø§Ù„ØªØ­ÙƒÙ… Ø¨Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¯Ø§Ø®Ù„ Ø§Ù„ÙƒÙ†ØªØ±ÙˆÙ„Ø±.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScopes()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø±ØµÙŠØ¯ Ø®Ø²Ù†Ø© Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ù†Ø´Ø· (Active Branch Safe Balance)
     *   ÙŠØ±Ø¬Ø¹ Ø±ØµÙŠØ¯ Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ù†Ø´Ø· Ø§Ù„Ø­Ø§Ù„ÙŠ Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù….
     */
    public function getActiveBranchBalanceAttribute(): float
    {
        $activeCompanyId = app(\App\Services\CurrentCompanyResolver::class)->resolve() ?? $this->active_company_id ?? null;
        if (!$activeCompanyId) {
            return 0.0;
        }

        $activeBranchId = config('app.active_branch_id') ?? $this->branch_id;
        if ($activeBranchId === 'all') {
            $activeBranchId = $this->branch_id;
        }

        $cashBox = $this->getDefaultCashBoxForCompany($activeCompanyId, $activeBranchId);
        return $cashBox ? (float) $cashBox->balance : 0.0;
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø±ØµÙŠØ¯ Ø§Ù„Ù…ØªÙˆØ§ÙÙ‚ Ù…Ø¹ Ø§Ù„Ù…Ø¹Ù…Ø§Ø±ÙŠØ© Ø§Ù„Ø¬Ø¯ÙŠØ¯Ø© (Ø§Ù„Ù…ÙˆØ¸Ù -> Ø¹Ù‡Ø¯Ø© | Ø§Ù„Ø¹Ù…ÙŠÙ„ -> Ø°Ù…Ø© Ù…Ø¯ÙŠÙ†Ø© | Ø§Ù„Ù…ÙˆØ±Ø¯ -> Ø°Ù…Ø© Ø¯Ø§Ø¦Ù†Ø©)
     */
    public function getBalanceAttribute()
    {
        $companyId = $this->active_company_id ?? $this->company_id;
        if (!$companyId) {
            return 0.0;
        }

        $isEmployee = false;
        try {
            $isEmployee = $this->hasCapability('is_internal', $companyId)
                || (function_exists('perm_key') && $this->can(perm_key('admin.super')))
                || (function_exists('perm_key') && $this->can(perm_key('admin.company')));
        } catch (\Throwable $e) {
            $isEmployee = false;
        }
        if ($isEmployee) {
            return $this->active_branch_balance;
        }

        $receivable = $this->getFinancialBalance($companyId, 'receivable');
        $payable    = $this->getFinancialBalance($companyId, 'payable');

        return $receivable > 0 ? $receivable : -$payable;
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø£Ø±ØµØ¯Ø© Ø§Ù„ØµÙ†Ø§Ø¯ÙŠÙ‚ ÙÙŠ Ø§Ù„ÙØ±ÙˆØ¹ Ø§Ù„Ù…Ù†ØªÙ…ÙŠ Ø¥Ù„ÙŠÙ‡Ø§ Ø¨Ø´Ø±Ø· Ø£Ù† ÙŠÙƒÙˆÙ† Ù…Ø±ØªØ¨Ø·Ø§Ù‹ Ø¨Ø£ÙƒØ«Ø± Ù…Ù† ÙØ±Ø¹
     *   ÙŠØ±Ø¬Ø¹ Ù…Ø¬Ù…ÙˆØ¹ Ø£Ø±ØµØ¯Ø© ÙƒÙ„ Ø®Ø²Ù† ÙØ±ÙˆØ¹ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¨Ø´Ø±Ø· Ø§Ø±ØªØ¨Ø§Ø·Ù‡ Ø¨Ø£ÙƒØ«Ø± Ù…Ù† ÙØ±Ø¹.
     */
    public function getCustodyBalanceAttribute(): ?float
    {
        $activeCompanyId = app(\App\Services\CurrentCompanyResolver::class)->resolve() ?? $this->active_company_id;
        if (!$activeCompanyId) {
            return 0.0;
        }
        return $this->getFinancialBalance($activeCompanyId, 'custody');
    }
    public function getTotalBranchesBalanceAttribute(): ?float
    {
        $activeCompanyId = app(\App\Services\CurrentCompanyResolver::class)->resolve() ?? $this->active_company_id;

        if (!$activeCompanyId) {
            return 0.0;
        }

        // Ø­Ø³Ø§Ø¨ Ø¹Ø¯Ø¯ Ø§Ù„ÙØ±ÙˆØ¹ Ø§Ù„Ù…Ø±ØªØ¨Ø· Ø¨Ù‡Ø§ ÙÙŠ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©
        $branchesCount = $this->branches()->count();

        if ($branchesCount <= 1) {
            return null; // Ø§Ù„Ø´Ø±Ø·: Ø¥Ø±Ø¬Ø§Ø¹Ù‡Ø§ ÙÙ‚Ø· Ø¥Ø°Ø§ ÙƒØ§Ù† Ù„Ù‡ Ø£ÙƒØ«Ø± Ù…Ù† ÙØ±Ø¹ Ù…Ø±ØªØ¨Ø·
        }

        if ($this->relationLoaded('cashBoxes') && $this->cashBoxes !== null) {
            return (float) collect($this->cashBoxes)
                ->where('company_id', $activeCompanyId)
                ->sum('balance');
        }

        return (float) $this->cashBoxes()
            ->where('company_id', $activeCompanyId)
            ->sum('balance');
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ù…Ø¹Ø±Ù Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø© ÙƒØ¨Ø¯ÙŠÙ„ Ù„Ù€ company_id Ù„Ø¶Ù…Ø§Ù† Ø§Ù„ØªÙˆØ§ÙÙ‚ÙŠØ© Ù…Ø¹ Ø§Ù„Ù…ÙˆØ¯ÙŠÙˆÙ„Ø§Øª Ø§Ù„Ù…Ø®ØªÙ„ÙØ©
     */
    public function getCompanyIdAttribute()
    {
        return app(\App\Services\CurrentCompanyResolver::class)->resolve() ?? $this->active_company_id;
    }

    /**
     *   ØªØ¹ÙŠÙŠÙ† Ù…Ø¹Ø±Ù Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø© ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¹Ù†Ø¯ ØªÙ…Ø±ÙŠØ± company_id Ù„Ø¶Ù…Ø§Ù† Ø§Ù„ØªÙˆØ§ÙÙ‚ÙŠØ© Ù…Ø¹ Ø§Ù„Ø¬Ø¯Ø§ÙˆÙ„ ÙˆØ§Ù„Ø§Ø®ØªØ¨Ø§Ø±Ø§Øª.
     */
    public function setCompanyIdAttribute($value)
    {
        $this->attributes['active_company_id'] = $value;
    }

    public function stakeholderFinancialBalances()
    {
        return $this->hasMany(\Modules\Companies\Models\StakeholderFinancialBalance::class);
    }

    protected array $financialBalanceCache = [];

    public function getFinancialBalance($companyId, $relationType = 'receivable'): float
    {
        $cacheKey = "{$companyId}_{$relationType}";
        if (array_key_exists($cacheKey, $this->financialBalanceCache)) {
            return $this->financialBalanceCache[$cacheKey];
        }

        if ($this->relationLoaded('stakeholderFinancialBalances')) {
            $bal = collect($this->stakeholderFinancialBalances)
                ->where('company_id', $companyId)
                ->where('relation_type', $relationType)
                ->first();
        } else {
            $bal = $this->stakeholderFinancialBalances()
                ->where('company_id', $companyId)
                ->where('relation_type', $relationType)
                ->first();
        }

        $balanceValue = $bal ? (float)$bal->balance : 0.00;
        $this->financialBalanceCache[$cacheKey] = $balanceValue;

        return $balanceValue;
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ø¹Ù„Ø§Ù‚Ø§Øª Ù„Ù„Ø´Ø±ÙƒØ© Ù…Ø¹ Ø¯Ø¹Ù… Ø§Ù„ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ø³Ø¨Ù‚.
     */
    public function getRelationTypesForCompany($companyId): array
    {
        if ($this->relationLoaded('businessRelations')) {
            return collect($this->businessRelations)
                ->where('company_id', $companyId)
                ->pluck('relation_type')
                ->toArray();
        }
        return $this->businessRelations()
            ->where('company_id', $companyId)
            ->pluck('relation_type')
            ->toArray();
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ù‚Ø¯Ø±Ø§Øª ÙˆØ§Ù„Ø³Ù„ÙˆÙƒÙŠØ§Øª Ø§Ù„ØªØ´ØºÙŠÙ„ÙŠØ© Ù„Ù„Ø´Ø±ÙƒØ© Ù…Ø¹ Ø¯Ø¹Ù… Ø§Ù„ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ø³Ø¨Ù‚.
     */
    public function getCapabilitiesForCompany($companyId): array
    {
        if ($this->relationLoaded('businessRelations')) {
            return collect($this->businessRelations)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->flatMap(function ($relation) {
                    if ($relation->relationLoaded('relationType') && $relation->relationType) {
                        if ($relation->relationType->relationLoaded('capabilities')) {
                            return collect($relation->relationType->capabilities)->pluck('code');
                        }
                    }
                    return $relation->relationType?->capabilities()->pluck('code')->toArray() ?? [];
                })
                ->unique()
                ->values()
                ->toArray();
        }

        return $this->businessRelations()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('relationType.capabilities')
            ->with('relationType.capabilities')
            ->get()
            ->flatMap(fn($relation) => $relation->relationType?->capabilities ? $relation->relationType->capabilities->pluck('code') : [])
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ù…Ø³Ù…Ù‰ Ø§Ù„ÙˆØ¸ÙŠÙÙŠ (Context-Aware)
     */
    public function getPositionAttribute($value)
    {
        return $this->activeCompanyUser?->position_in_company ?? $value;
    }

    /**
     * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ù†ÙˆØ¹ Ø§Ù„Ø¹Ù…ÙŠÙ„ (Context-Aware)
     */
    public function getCustomerTypeAttribute($value)
    {
        return $this->activeCompanyUser?->customer_type_in_company ?? $value;
    }

    /**
     * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù…Ø§ Ø¥Ø°Ø§ ÙƒØ§Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙŠÙ…Ù„Ùƒ Ø£ÙŠ ØµÙ„Ø§Ø­ÙŠØ§Øª (ÙŠØ¹ØªØ¨Ø± Ù…ÙˆØ¸Ù Ø£Ùˆ Ù…Ø¯ÙŠØ±).
     */
    public function isStaffOrAdmin(): bool
    {
        // ÙØ­Øµ Ù…Ø§ Ø¥Ø°Ø§ ÙƒØ§Ù† Ù„Ø¯ÙŠÙ‡ ØµÙ„Ø§Ø­ÙŠØ© Ø³ÙˆØ¨Ø± Ø£Ø¯Ù…Ù† Ø¹Ø§Ù„Ù…ÙŠØ©
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return true;
        }

        // ÙØ­Øµ Ù…Ø§ Ø¥Ø°Ø§ ÙƒØ§Ù† Ù„Ø¯ÙŠÙ‡ Ø£ÙŠ ØµÙ„Ø§Ø­ÙŠØ§Øª Ù…Ø¨Ø§Ø´Ø±Ø© Ø£Ùˆ Ø£Ø¯ÙˆØ§Ø±
        return $this->permissions()->count() > 0 || $this->roles()->count() > 0;
    }

    /**
     * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù…Ø§ Ø¥Ø°Ø§ ÙƒØ§Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù‡Ùˆ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ø§Ù„Ù†Ù‚Ø¯ÙŠ Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠ Ù„Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø© Ø£Ùˆ Ø´Ø±ÙƒØ© Ù…Ø¹ÙŠÙ†Ø©.
     *
     * @param int|null $companyId
     * @return bool
     */
    public function isDefaultCashCustomer($companyId = null): bool
    {
        $companyId = $companyId ?? $this->active_company_id ?? \Auth::user()?->active_company_id ?? null;
        if (!$companyId) {
            return false;
        }

        $company = Company::find($companyId);
        return $company && (int) $company->default_cash_customer_id === (int) $this->id;
    }

    /**
     * Accessor Ù„Ù„ØªØ­Ù‚Ù‚ Ø§Ù„Ø³Ù‡Ù„ ÙˆØ§Ù„Ø¢Ù„ÙŠ Ù…Ù† Ø§Ù„Ø¹Ù…ÙŠÙ„ Ø§Ù„Ù†Ù‚Ø¯ÙŠ Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠ.
     *
     * @return bool
     */
    public function getIsDefaultCashCustomerAttribute(): bool
    {
        return $this->isDefaultCashCustomer();
    }
}
