<?php

namespace Modules\Store\Models;

// موديل عنوان شحن العميل في المتجر الإلكتروني
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone',
        'country', 'city', 'district', 'street',
        'building', 'floor', 'apartment', 'landmark', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
