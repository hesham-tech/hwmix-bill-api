<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Image;

use App\Traits\LogsActivity;

class PaymentMethod extends Model
{
    use HasFactory, Scopes, Blameable, SoftDeletes, LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "طريقة الدفع ({$this->name})";
    }

    protected $fillable = ['name', 'code', 'active', 'is_system', 'company_id', 'created_by', 'updated_by'];

    protected $casts = [
        'active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope للحصول على طرق الدفع النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
