<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorReport extends Model
{
    use HasFactory, \App\Traits\FilterableByBranch;

    protected $fillable = [
        'user_id',
        'company_id',
        'type',
        'message',
        'stack_trace',
        'url',
        'browser',
        'os',
        'user_notes',
        'payload',
        'screenshot_url',
        'status',
        'severity',
        'branch_id',
    ];

    protected static function booted()
    {
        static::creating(function ($report) {
            $report->company_id = $report->company_id ?? auth()->user()->company_id ?? null;
            $report->branch_id = $report->branch_id ?? config('app.active_branch_id') ?? auth()->user()->branch_id ?? null;
        });
    }

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
