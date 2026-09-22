<?php

namespace Modules\Store\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Modules\Inventory\Models\Product;

/**
 * موديل المفضلة للمتجر (Store Wishlist)
 */
class StoreWishlist extends Model
{
    protected $table = 'store_wishlists';

    protected $fillable = [
        'user_id',
        'product_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
