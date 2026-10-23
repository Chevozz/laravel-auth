<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_portable_catalog_seed_is_complete_idempotent_and_has_no_default_user(): void
    {
        Storage::fake('public');
        $this->seed();
        $this->assertDatabaseCount('products', 12);
        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseCount('users', 0);
        $ids = Product::pluck('id', 'slug')->all();
        $this->seed();
        $this->assertDatabaseCount('products', 12);
        $this->assertDatabaseCount('categories', 5);
        $this->assertSame($ids, Product::pluck('id', 'slug')->all());
        $fixture = json_decode(file_get_contents(database_path('seeders/data/products.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($fixture as $source) {
            $product = Product::where('slug', $source['slug'])->firstOrFail();
            foreach (['name', 'description', 'details', 'stock', 'sold'] as $field) {
                $this->assertSame($source[$field], $product->$field);
            }
            $this->assertEquals($source['price'], $product->price);
            $this->assertEquals($source['rating'], $product->rating);
            $this->assertSame($source['category'], $product->category->name);
            Storage::disk('public')->assertExists($product->image);
            $this->assertSame(file_get_contents(database_path('seeders/images/'.$source['image'])), Storage::disk('public')->get($product->image));
        }
    }

    public function test_category_foreign_key_restricts_deletion(): void
    {
        Storage::fake('public');
        $this->seed();
        $this->expectException(QueryException::class);
        Category::first()->delete();
    }
}
