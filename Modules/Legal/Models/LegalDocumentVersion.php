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
 * نموذج يمثل إصدارات المستندات القانونية (مثل مسودة، منشور، مؤرشف) مع تتبع سجل التغييرات ومحتوى الشروط.
 */
class LegalDocumentVersion extends Model
{
    use SoftDeletes, Blameable, LogsActivity;

    protected $table = 'legal_document_versions';

    protected $fillable = [
        'legal_document_id',
        'version',
        'title',
        'content',
        'summary',
        'status',
        'published_at',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * تعليق توضيحي للاستخدام في سجلات الأنشطة
     */
    public function logLabel(): string
    {
        return "الإصدار '{$this->version}' للمستند '{$this->title}'";
    }

    /**
     * تطبيق الفلترة لنظام الـ Multi-Tenant مع السماح بالمستندات العامة (company_id is null)
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('company_or_global_version', function (Builder $builder) {
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

    public function document()
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function acceptances()
    {
        return $this->hasMany(LegalDocumentAcceptance::class, 'legal_document_version_id');
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
