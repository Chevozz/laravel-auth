<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_update_delete_and_categories(): void
    {
        $this->signIn();
        $data = $this->payload();
        $id = $this->postJson('/api/products', $data)->json('data.id');
        $this->getJson('/api/products')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/products/'.$id)->assertOk()->assertJsonPath('data.slug', 'cup');
        $this->getJson('/api/categories')->assertOk()->assertJsonPath('data.0.name', 'Dapur');
        $this->patchJson('/api/products/'.$id, ['name' => 'New Cup', 'slug' => 'cup'])->assertOk()->assertJsonPath('data.name', 'New Cup')->assertJsonPath('data.stock', 4);
        $this->putJson('/api/products/'.$id, array_replace($data, ['stock' => 8]))->assertOk()->assertJsonPath('data.stock', 8);
        $this->deleteJson('/api/products/'.$id)->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseMissing('products', ['id' => $id]);
        foreach (['get', 'patch', 'put', 'delete'] as $method) {
            $this->{$method.'Json'}('/api/products/'.$id)->assertNotFound();
        }
    }

    public function test_all_product_routes_require_authentication_without_accept_header(): void
    {
        foreach (['GET' => '/api/products', 'POST' => '/api/products', 'PUT' => '/api/products/999', 'PATCH' => '/api/products/999', 'DELETE' => '/api/products/999'] as $method => $url) {
            $this->call($method, $url)->assertUnauthorized()->assertJsonStructure(['message']);
        }
        $this->get('/api/products/999')->assertUnauthorized();
        $this->get('/api/categories')->assertUnauthorized();
    }

    public function test_validation_bounds_unique_slug_and_partial_patch(): void
    {
        $this->signIn();
        $this->postJson('/api/products', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'slug', 'price', 'stock', 'category_id']);
        $data = $this->payload();
        $id = $this->postJson('/api/products', $data)->json('data.id');
        $this->postJson('/api/products', $data)->assertUnprocessable()->assertJsonValidationErrors('slug');
        foreach (['price' => [-1, 10000000000, 1.234], 'stock' => [-1, 1.5, 4294967296], 'sold' => [-1, 1.5, 4294967296], 'rating' => [-1, 5.1, 4.55], 'category_id' => [99999], 'slug' => ['Bad Slug'], 'details' => ['not array', [123]], 'name' => ['']] as $field => $values) {
            foreach ($values as $value) {
                $this->patchJson('/api/products/'.$id, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field === 'details' && is_array($value) ? 'details.0' : $field);
            }
        }
    }

    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'jeda');
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(fn () => is_file($path) ? unlink($path) : null);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function image(): UploadedFile
    {
        return $this->upload('pixel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    }

    public function test_image_upload_replace_retain_delete_and_spoofed_put(): void
    {
        Storage::fake('public');
        $this->signIn();
        $data = $this->payload();
        $response = $this->post('/api/products', $data + ['image' => $this->image()])->assertCreated();
        $id = $response->json('data.id');
        $old = Product::find($id)->image;
        $this->assertNotNull($old);
        Storage::disk('public')->assertExists($old);
        $this->patchJson('/api/products/'.$id, ['name' => 'Retained'])->assertOk();
        $this->assertSame($old, Product::find($id)->image);
        $this->post('/api/products/'.$id, $data + ['_method' => 'PUT', 'image' => $this->image()])->assertOk();
        $new = Product::find($id)->image;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old)->assertExists($new);
        $this->deleteJson('/api/products/'.$id)->assertOk();
        Storage::disk('public')->assertMissing($new);
    }

    public function test_invalid_images_do_not_replace_existing_file(): void
    {
        Storage::fake('public');
        $this->signIn();
        $id = $this->post('/api/products', $this->payload() + ['image' => $this->image()])->json('data.id');
        $old = Product::find($id)->image;
        foreach ([UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'), UploadedFile::fake()->create('large.png', 2049, 'image/png'), $this->upload('fake.png', 'not an image'), 'products/arbitrary.png'] as $image) {
            $this->post('/api/products/'.$id, ['_method' => 'PATCH', 'image' => $image])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertSame($old, Product::find($id)->image);
        Storage::disk('public')->assertExists($old);
    }

    public function test_shared_image_survives_replacement_and_deletion_until_unreferenced(): void
    {
        Storage::fake('public');
        $this->signIn();
        $id = $this->post('/api/products', $this->payload() + ['image' => $this->image()])->json('data.id');
        $first = Product::find($id);
        $shared = $first->image;
        $this->assertNotNull($shared);
        $second = $first->replicate();
        $second->slug = 'second';
        $second->save();
        $third = $first->replicate();
        $third->slug = 'third';
        $third->save();
        $this->post('/api/products/'.$id, ['_method' => 'PATCH', 'image' => $this->image()])->assertOk();
        Storage::disk('public')->assertExists($shared);
        $this->deleteJson('/api/products/'.$second->id)->assertOk();
        Storage::disk('public')->assertExists($shared);
        $this->deleteJson('/api/products/'.$third->id)->assertOk();
        Storage::disk('public')->assertMissing($shared);
    }

    public function test_database_failure_removes_new_upload_and_preserves_old_image(): void
    {
        Storage::fake('public');
        $this->signIn();
        $id = $this->post('/api/products', $this->payload() + ['image' => $this->image()])->json('data.id');
        $old = Product::find($id)->image;
        DB::unprepared("CREATE TRIGGER fail_product_update BEFORE UPDATE ON products BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
        $this->post('/api/products/'.$id, ['_method' => 'PATCH', 'image' => $this->image()])->assertStatus(500);
        $this->assertSame([$old], Storage::disk('public')->allFiles('products'));
        $this->assertSame($old, Product::find($id)->image);
    }

    protected function signIn(): void
    {
        $this->withToken(User::factory()->create()->createToken('test')->plainTextToken);
    }

    protected function payload(array $overrides = []): array
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Dapur', 'slug' => 'dapur-'.uniqid()]);

        return array_replace(['name' => 'Cup', 'slug' => 'cup', 'price' => 12000.50, 'category_id' => $category, 'stock' => 4, 'details' => ['Handmade'], 'rating' => 4.8, 'sold' => 20, 'description' => 'Clay cup'], $overrides);
    }

    public function test_create_product_returns_complete_resource_and_category_relationship(): void
    {
        $this->signIn();
        $data = $this->payload();
        $response = $this->postJson('/api/products', $data)->assertCreated();
        $response->assertJsonPath('data.category', 'Dapur')->assertJsonPath('data.price', '12000.50')->assertJsonPath('data.details', ['Handmade'])->assertJsonStructure(['data' => ['id', 'name', 'slug', 'price', 'category', 'category_id', 'image', 'stock', 'details', 'rating', 'sold', 'description']]);
        $this->assertDatabaseHas('products', ['id' => $response->json('data.id'), 'slug' => 'cup']);
        $this->assertCount(1, Category::first()->products);
    }
}
