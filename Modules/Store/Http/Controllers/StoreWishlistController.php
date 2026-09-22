<?php

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Store\Models\StoreWishlist;
use Modules\Store\Transformers\StoreProductResource;

/**
 * متحكم المفضلة الخاص بالمتجر الإلكتروني
 */
class StoreWishlistController extends Controller
{
    /**
     * جلب قائمة منتجات المفضلة للعميل
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $wishlists = StoreWishlist::where('user_id', $user->id)
            ->with(['product' => function($q) {
                $q->withoutGlobalScopes()
                  ->where('active', true)
                  ->where('is_active_in_store', true)
                  ->with([
                      'images', 'category', 'brand', 'company.logo',
                      'variants' => fn($vq) => $vq->withoutGlobalScopes()->where('status', 'active')->with(['stocks' => fn($sq) => $sq->withoutGlobalScopes()])
                  ]);
            }])
            ->get()
            ->pluck('product')
            ->filter();

        return response()->json([
            'status' => 'success',
            'data' => StoreProductResource::collection($wishlists)
        ]);
    }

    /**
     * الحصول على مصفوفة بأرقام المنتجات المفضلة فقط
     */
    public function getIds(Request $request)
    {
        $user = $request->user();
        
        $ids = StoreWishlist::where('user_id', $user->id)->pluck('product_id');

        return response()->json([
            'status' => 'success',
            'data' => $ids
        ]);
    }

    /**
     * تبديل حالة المفضلة (إضافة / إزالة)
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = $request->user();
        $productId = $request->product_id;

        $wishlist = StoreWishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($wishlist) {
            $wishlist->delete();
            $status = 'removed';
            $message = 'تمت الإزالة من المفضلة';
        } else {
            StoreWishlist::create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
            $status = 'added';
            $message = 'تمت الإضافة للمفضلة';
        }

        return response()->json([
            'status' => 'success',
            'action' => $status,
            'message' => $message,
        ]);
    }
}
