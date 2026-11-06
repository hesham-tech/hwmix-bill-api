<?php
namespace Modules\Store\Transformers;
use Illuminate\Http\Resources\Json\JsonResource;
class StoreProductResource extends JsonResource {
    public function toArray($request) {
        $variants=$this->variants->filter(fn($variant)=>(int)$variant->company_id===(int)$this->company_id)->map(function($variant) {
            $base=$variant->base_unit_id?:$this->base_unit_id;
            $unit=$variant->display_unit_id?:($this->display_unit_id?:$base);
            $factor='1';
            if(!$base||!$unit)$factor='0';
            elseif((int)$unit!==(int)$base)$factor=(string)($variant->units->firstWhere('unit_id',$unit)?->getRawOriginal('conversion_factor_to_base')??'0');
            $available=$variant->stocks->filter(fn($stock)=>(int)$stock->company_id===(int)$variant->company_id)->reduce(fn($sum,$stock)=>bcadd($sum,bcsub($stock->quantity,$stock->reserved,6),6),'0');
            $quantity=is_numeric($factor)&&bccomp($factor,'0',6)>0?max(0,(int)bcdiv($available,$factor,0)):0;
            $unitName=(int)$unit===(int)$variant->display_unit_id?$variant->displayUnit?->name:((int)$unit===(int)$this->display_unit_id?$this->displayUnit?->name:($variant->baseUnit?->name??$this->baseUnit?->name));
            return ['id'=>$variant->id,'name'=>$variant->name,'sku'=>$variant->sku,'price'=>(float)$variant->retail_price,
                'barcode'=>$variant->barcode,'weight'=>$variant->weight,'dimensions'=>$variant->dimensions,'warranty_days'=>$variant->warranty_days,
                'selling_unit_id'=>$unit,'unit_name'=>$unitName,'conversion_factor'=>$factor,
                'image'=>$variant->images->first()?->url??$variant->image??null,'images'=>$variant->images->map(fn($image)=>['id'=>$image->id,'url'=>$image->url]),
                'attributes'=>$variant->attributes->map(fn($attribute)=>['name'=>$attribute->attribute?->name??'','value'=>$attribute->attributeValue?->value??$attribute->attributeValue?->name??$attribute->value??'']),
                'available_stock'=>$quantity];
        })->sortBy(fn($variant)=>[$variant['price'],$variant['id']])->values();
        $default=$variants->first(fn($variant)=>$variant['available_stock']>0)??$variants->first();
        $price=$default['price']??0;$quantity=$variants->sum('available_stock');
        return ['id'=>$this->id,'company_id'=>$this->company_id,'name'=>$this->name,'sku'=>$this->sku,'description'=>$this->desc,'desc_long'=>$this->desc_long,
            'category_id'=>$this->category_id,'category'=>$this->category?['id'=>$this->category->id,'name'=>$this->category->name]:null,
            'brand'=>$this->brand?['id'=>$this->brand->id,'name'=>$this->brand->name]:null,
            'unit'=>isset($default['selling_unit_id'])?['id'=>$default['selling_unit_id'],'name'=>$default['unit_name']]:null,
            'price'=>$price,'discount'=>0,'old_price'=>$price,'rating'=>0,'reviews_count'=>0,
            'image'=>$this->images->first()?->url,'images'=>$this->images->map(fn($image)=>['id'=>$image->id,'url'=>$image->url]),
            'quantity'=>$quantity,'stock_status'=>$quantity>0?'in_stock':'out_of_stock','default_variant_id'=>$default['id']??null,
            'vendor'=>new VendorResource($this->whenLoaded('company')),'variants'=>$variants];
    }
}
