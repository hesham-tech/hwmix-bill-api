<?php

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Models\CustomerAddress;
use Modules\Store\Http\Requests\StoreAddressRequest;
use Modules\Store\Transformers\CustomerAddressResource;
use Illuminate\Support\Facades\Auth;

class CustomerAddressController extends Controller
{
    /**
     * عرض عناوين العميل الحالي
     */
    public function index()
    {
        $addresses = CustomerAddress::where('user_id', Auth::id())->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب العناوين بنجاح',
            'data' => CustomerAddressResource::collection($addresses)
        ]);
    }

    /**
     * إضافة عنوان جديد
     */
    public function store(StoreAddressRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        if (isset($data['is_default']) && $data['is_default']) {
            CustomerAddress::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        $address = CustomerAddress::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العنوان بنجاح',
            'data' => new CustomerAddressResource($address)
        ]);
    }

    /**
     * تحديث عنوان موجود
     */
    public function update(StoreAddressRequest $request, $id)
    {
        $address = CustomerAddress::where('user_id', Auth::id())->findOrFail($id);
        
        $data = $request->validated();

        if (isset($data['is_default']) && $data['is_default']) {
            CustomerAddress::where('user_id', Auth::id())->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العنوان بنجاح',
            'data' => new CustomerAddressResource($address)
        ]);
    }

    /**
     * حذف عنوان
     */
    public function destroy($id)
    {
        $address = CustomerAddress::where('user_id', Auth::id())->findOrFail($id);
        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العنوان بنجاح',
            'data' => null
        ]);
    }
}
