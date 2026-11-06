<?php
namespace Modules\Payment\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Services\GatewayRegistry;
use Modules\Payment\Services\PaymentGatewayFactory;
class PaymentGatewayResource extends JsonResource {
    public function toArray($request): array {
        $definition=GatewayRegistry::definition($this->driver);
        $config=PaymentGatewayFactory::configuration($this->resource);
        $public=$configured=[];
        foreach($definition['fields'] as $key=>$type) {
            if($type==='secret') $configured[$key]=!empty($config[$key]);
            else $public[$key]=$config[$key]??null;
        }
        return [
            'id'=>$this->id,'name'=>$this->name,'driver'=>$this->driver,'config'=>$public,
            'credentials_configured'=>$configured,'ready'=>GatewayRegistry::ready($this->driver,$config),
            'is_active'=>(bool)$this->is_active,'is_test_mode'=>(bool)$this->is_test_mode,
            'is_default'=>(bool)$this->is_default,'company_id'=>$this->company_id,
            'created_at'=>$this->created_at,'updated_at'=>$this->updated_at,
        ];
    }
}
