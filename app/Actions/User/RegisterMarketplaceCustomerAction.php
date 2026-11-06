<?php

/**
 * كلاس مسؤول عن تسجيل عملاء المتجر (Marketplace)
 * ينشئ هوية عالمية فقط دون ربطها بأي شركة في البداية (Lazy ERP)
 */

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class RegisterMarketplaceCustomerAction
{
    /**
     * تنفيذ عملية التسجيل
     *
     * @param array $data بيانات العميل
     * @return User
     * @throws Throwable
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $documents=app(\Modules\Store\Services\StoreRegistrationLegalService::class)->documents();
            $expected=array_column($documents,'id'); sort($expected);
            $accepted=array_map('intval',$data['legal_version_ids']??[]); sort($accepted);
            if (!$expected || $expected!==$accepted || empty($data['legal_accepted'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['legal_version_ids'=>'راجع شروط الاستخدام وسياسة الخصوصية المنشورتين قبل التسجيل.']);
            }
            // 1. إنشاء المستخدم العالمي
            $user = User::withoutEvents(fn () => User::create([
                'phone' => $data['phone'],
                'email' => strtolower(trim($data['email'])),
                'full_name' => $data['full_name'],
                'nickname' => $data['nickname'] ?? $data['full_name'],
                'password' => Hash::make($data['password']),
                'username' => $data['username'] ?? $data['phone'],
                'company_id'=>null,'active_company_id'=>null,
            ]));

            foreach ($documents as $document) \Modules\Legal\Models\LegalDocumentAcceptance::create([
                'user_id'=>$user->id,'legal_document_version_id'=>$document['id'],'accepted_at'=>now('UTC'),
                'ip_address'=>request()?->ip(),'user_agent'=>substr((string)request()?->userAgent(),0,1000),'created_by'=>$user->id,
            ]);
            return $user;
        });
    }
}
