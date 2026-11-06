<?php
namespace Modules\Store\Services;
use App\Models\User;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\StoreEmailVerification;

class StoreAccountService {
    public function __construct(private StoreOutboxService $outbox) {}
    public function request(User $user,?string $email=null,?string $password=null): array {
        $email=strtolower(trim($email?:$user->email?:''));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))
            throw ValidationException::withMessages(['email'=>'أدخل بريدًا إلكترونيًا صالحًا.']);
        if($user->email_verified_at&&strtolower($user->email)===$email) return ['verified'=>true,'email'=>$user->email];
        if($email!==strtolower($user->email??'')&&(!is_string($password)||!Hash::check($password,$user->password)))
            throw ValidationException::withMessages(['password'=>'أكد كلمة المرور لإضافة بريد جديد إلى الحساب.']);
        if(User::withoutGlobalScopes()->where('email',$email)->where('id','!=',$user->id)->exists())
            throw ValidationException::withMessages(['email'=>'لا يمكن استخدام هذا البريد لهذا الحساب.']);
        return DB::transaction(function() use($user,$email) {
            User::withoutGlobalScopes()->lockForUpdate()->findOrFail($user->id);
            $current=StoreEmailVerification::where('user_id',$user->id)->first();
            if($current&&$current->email===$email&&$current->sent_at->gt(now('UTC')->subMinute())&&!$current->verified_at)
                return ['verified'=>false,'queued'=>true,'email'=>$email,'retry_after'=>max(1,60-(int)$current->sent_at->diffInSeconds(now('UTC')))];
            $code=(string)random_int(10000000,99999999);
            $verification=StoreEmailVerification::updateOrCreate(['user_id'=>$user->id],[
                'email'=>$email,'token_hash'=>hash_hmac('sha256',$code,config('app.key')),'expires_at'=>now('UTC')->addMinutes(15),
                'attempts'=>0,'sent_at'=>now('UTC'),'verified_at'=>null,
            ]);
            $entry=$this->outbox->enqueue('account.email_verification','email:'.$verification->id.':'.substr($verification->token_hash,0,20),[
                'verification_id'=>$verification->id,'code'=>$code,
            ]);
            DB::afterCommit(fn()=>\Modules\Store\Jobs\ProcessStoreOutboxEntry::dispatch($entry->id));
            return ['verified'=>false,'queued'=>true,'email'=>$email,'retry_after'=>60];
        },3);
    }
    public function verify(User $user,string $code): User {
        $valid=DB::transaction(function() use($user,$code) {
            $locked=User::withoutGlobalScopes()->lockForUpdate()->findOrFail($user->id);
            $verification=StoreEmailVerification::where('user_id',$user->id)->lockForUpdate()->first();
            if(!$verification||$verification->verified_at||$verification->expires_at->isPast()||$verification->attempts>=5) return false;
            $matches=hash_equals($verification->token_hash,hash_hmac('sha256',$code,config('app.key')));
            if(!$matches) { $verification->increment('attempts'); return false; }
            if(User::withoutGlobalScopes()->where('email',$verification->email)->where('id','!=',$user->id)->exists()) return false;
            $locked->forceFill(['email'=>$verification->email,'email_verified_at'=>now('UTC')])->save();
            $verification->update(['verified_at'=>now('UTC')]);
            return true;
        },3);
        if(!$valid) throw ValidationException::withMessages(['code'=>'الرمز غير صالح أو انتهت صلاحيته. اطلب رمزًا جديدًا.']);
        return $user->refresh();
    }
}
