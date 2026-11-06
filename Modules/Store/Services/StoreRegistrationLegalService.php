<?php
namespace Modules\Store\Services;
use Modules\Legal\Models\LegalDocument;
class StoreRegistrationLegalService {
    public function documents(): array {
        $keys=['terms-of-use','privacy-policy'];
        $documents=LegalDocument::withoutGlobalScopes()->whereNull('deleted_at')->where('is_active',true)
            ->whereIn('key',$keys)->where(fn($q)=>$q->whereNull('company_id')->orWhere('company_id',config('app.master_company_id',1)))
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')->with([
                'versions'=>fn($q)=>$q->withoutGlobalScopes()->whereNull('deleted_at')->where('status','published')
                    ->where('published_at','<=',now())->latest('id'),
            ])->get();
        $result=[];
        foreach($keys as $key) {
            $document=$documents->firstWhere('key',$key); $version=$document?->versions->first();
            if(!$version) return [];
            $result[]=['id'=>$version->id,'document_id'=>$document->id,'key'=>$key,'version'=>$version->version,
                'title'=>$version->title,'content'=>$version->content];
        }
        return $result;
    }
}
