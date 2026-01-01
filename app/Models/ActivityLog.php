<?php

namespace App\Models;

use App\Traits\Translations\Translatable;
use App\Traits\Blameable;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin IdeHelperActivityLog
 */
class ActivityLog extends Model
{
    use HasFactory, Notifiable, Translatable, HasRoles, HasApiTokens, Filterable, Scopes, RolePermissions, LogsActivity, Blameable;

    protected $fillable = [
        'action',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'description',
        'user_id',
        'created_by',
        'company_id',
        'user_agent',
        'ip_address',
        'url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}
