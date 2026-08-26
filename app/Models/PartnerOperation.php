<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;



    /**
     * قيود دفتر الأستاذ المرتبطة بهذه العملية
     */
    public function financialLedgers(): MorphMany
    {
        return $this->morphMany(FinancialLedger::class, 'source');
    }

    /**
     * المستخدم الذي أنشأ السجل
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستخدم الذي حدث السجل
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
