<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantAttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attribute_id' => $this->attribute_id,
            'attribute_value_id' => $this->attribute_value_id,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'attribute_value' => new AttributeValueResource($this->whenLoaded('attributeValue')),
        ];
    }
}
