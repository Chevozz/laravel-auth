<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'price', 'image', 'stock', 'details', 'rating', 'sold', 'description'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'rating' => 'decimal:1', 'details' => 'array', 'stock' => 'integer', 'sold' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
