<?php

namespace Modules\Legal\Models;

use App\Models\Company;
use App\Models\User;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

use App\Traits\LogsActivity;

/**
 * نموذج لتسجيل موافقة المستخدمين على إصدارات المستندات القانونية وتتبع عناوين الـ IP والبيانات الحيوية مع سجلات النشاط.
 */
class LegalDocumentAcceptance extends Model
{
    use Blameable, LogsActivity;

    protected $table = 'legal_document_acceptances';

    protected $fillable = [
        'user_id',
        'legal_document_version_id',
        'accepted_at',
        'ip_address',
        'user_agent',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /**
     * تطبيق الفلترة لنظام الـ Multi-Tenant مع السماح بالبيانات العامة (company_id is null)
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('company_or_global_acceptance', function (Builder $builder) {
            $user = Auth::user();
            if ($user) {
                if ($user->hasPermissionTo(perm_key('admin.super'))) {
                    return;
                }

                $activeCompanyId = $user->active_company_id;
                if ($activeCompanyId) {
                    $builder->where(function ($query) use ($activeCompanyId) {
                        $query->where('company_id', $activeCompanyId)
                              ->orWhereNull('company_id');
                    });
                } else {
                    $builder->whereNull('company_id');
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function version()
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
