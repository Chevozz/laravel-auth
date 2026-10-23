<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $products = json_decode(file_get_contents(__DIR__.'/data/products.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($products as $data) {
            Category::updateOrCreate(['slug' => Str::slug($data['category'])], ['name' => $data['category']]);
        }
    }
}
