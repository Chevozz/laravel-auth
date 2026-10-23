<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = json_decode(file_get_contents(__DIR__.'/data/products.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($products as $data) {
            $category = Category::where('slug', Str::slug($data['category']))->firstOrFail();
            // Only trusted bundled artwork can be SVG; API uploads reject it.
            $path = 'products/seed/'.$data['image'];
            if (! Storage::disk('public')->put($path, file_get_contents(__DIR__.'/images/'.$data['image']))) {
                throw new \RuntimeException('Unable to copy seed image: '.$data['image']);
            }
            unset($data['id'], $data['category']);
            $data['category_id'] = $category->id;
            $data['image'] = $path;
            Product::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
