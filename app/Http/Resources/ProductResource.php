<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'price' => $this->price,
            'category' => $this->category->name, 'category_id' => $this->category_id,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'stock' => $this->stock, 'details' => $this->details ?? [], 'rating' => $this->rating,
            'sold' => $this->sold, 'description' => $this->description];
    }
}
