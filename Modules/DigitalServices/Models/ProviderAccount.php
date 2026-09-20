<?php

namespace Modules\DigitalServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\DigitalServices\Database\Factories\ProviderAccountFactory;

class ProviderAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): ProviderAccountFactory
    // {
    //     // return ProviderAccountFactory::new();
    // }
}
