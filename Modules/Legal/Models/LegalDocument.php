<?php

namespace Modules\Legal\Models;

use App\Models\Company;
use App\Models\User;
use App\Traits\Blameable;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * نموذج لإدارة مستندات المنصة والشركات القانونية وتحديد حالتها النشطة.
 */
class LegalDocument extends Model
{
    use SoftDeletes, Blameable, LogsActivity;

    protected $table = 'legal_documents';

    protected $fillable = [
        'key',
        'is_active',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * تعليق توضيحي للاستخدام في سجلات الأنشطة
     */
    public function logLabel(): string
    {
        return "المستند القانوني '{$this->key}'";
    }

    /**
     * تطبيق الفلترة لنظام الـ Multi-Tenant مع السماح بالمستندات العامة (company_id is null)
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('company_or_global', function (Builder $builder) {
            $user = Auth::user();
            if ($user) {
                // If it is super admin, they can see everything (we do not enforce scope if they are super admin viewing all)
                // Let's check permission:
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
                    // Non-superadmin with no active company cannot see tenant-scoped documents, only global ones
                    $builder->whereNull('company_id');
                }
            }
        });
    }

    public function versions()
    {
        return $this->hasMany(LegalDocumentVersion::class, 'legal_document_id');
    }

    public function activeVersion()
    {
        return $this->hasOne(LegalDocumentVersion::class, 'legal_document_id')
            ->where('status', 'published');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
