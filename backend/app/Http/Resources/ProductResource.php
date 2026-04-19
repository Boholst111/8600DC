<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'brand' => $this->brand,
            'scale' => $this->scale,
            'series' => $this->series,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'is_limited_edition' => $this->is_limited_edition,
            'has_opening_parts' => $this->has_opening_parts,
            'tire_type' => $this->tire_type,
            'is_preorder' => $this->is_preorder,
            'eta' => $this->eta,
            'downpayment_amount' => (float) $this->downpayment_amount,
            'image_url' => $this->image_url,
            'images' => $this->images->map(fn($img) => [
                'id' => $img->id,
                'path' => $img->path,
                'sort_order' => $img->sort_order,
            ]),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'preorder_info' => $this->whenLoaded('preorder', function () {
                return [
                    'release_date' => $this->preorder->release_date,
                    'downpayment_amount' => (float) $this->preorder->downpayment_amount,
                    'is_active' => $this->preorder->is_active,
                ];
            }),
            'availability' => $this->stock > 0 ? 'In Stock' : ($this->is_preorder ? 'Pre-order' : 'Out of Stock'),
        ];
    }
}
