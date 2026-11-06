<?php
namespace Modules\Store\Services;
use Modules\Legal\Models\LegalDocument;
class StoreRegistrationLegalService {
    public function documents(): array {
        $settings=app(StoreSettingsService::class)->get();
        $ids=$settings['legal_document_ids'];
        // Once store documents are selected, never silently substitute ERP terms.
        if(!$ids && $settings['enabled']) return [];
        $groups=$ids
            ? [['store-terms-of-sale','terms-of-use'],['store-privacy-policy','privacy-policy']]
            : [['terms-of-use'],['privacy-policy']];
        $keys=array_merge(...$groups);
        $documents=LegalDocument::withoutGlobalScopes()->whereNull('deleted_at')->where('is_active',true)
            ->when($ids,fn($q)=>$q->whereIn('id',$ids))
            ->whereIn('key',$keys)->where(fn($q)=>$q->whereNull('company_id')->orWhere('company_id',config('app.master_company_id',1)))
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')->with([
                'versions'=>fn($q)=>$q->withoutGlobalScopes()->whereNull('deleted_at')->where('status','published')
                    ->where('published_at','<=',now())->latest('id'),
            ])->get();
        $result=[];
        foreach($groups as $group) {
            $document=null;
            foreach($group as $key) {
                $candidate=$documents->firstWhere('key',$key);
                if($candidate?->versions->first()) {$document=$candidate;break;}
            }
            $version=$document?->versions->first();
            if(!$version) return [];
            $result[]=['id'=>$version->id,'document_id'=>$document->id,'key'=>$document->key,'version'=>$version->version,
                'title'=>$version->title,'content'=>$version->content];
        }
        return $result;
    }
}
