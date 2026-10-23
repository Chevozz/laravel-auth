<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        return ProductResource::collection(Product::with('category')->orderBy('id')->get());
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load('category'));
    }

    public function store(Request $request)
    {
        return $this->save($request, new Product);
    }

    public function update(Request $request, Product $product)
    {
        return $this->save($request, $product);
    }

    public function destroy(Product $product)
    {
        $old = $product->image;
        DB::transaction(fn () => $product->delete());
        $this->deleteUnsharedImage($old);

        return response()->json(['data' => null]);
    }

    private function save(Request $request, Product $product)
    {
        $data = $this->validated($request, $product->exists ? $product : null);
        $old = $product->image;
        $new = null;
        unset($data['image']);
        try {
            if ($request->hasFile('image')) {
                $new = $request->file('image')->store('products', 'public');
                if (! $new) {
                    throw new \RuntimeException('Image storage failed.');
                }
                $data['image'] = $new;
            }
            DB::transaction(function () use ($product, $data) {
                $product->fill($data)->save();
            });
        } catch (\Throwable $e) {
            if ($new) {
                Storage::disk('public')->delete($new);
            }
            throw $e;
        }
        if ($new) {
            $this->deleteUnsharedImage($old);
        }

        return new ProductResource($product->refresh()->load('category'));
    }

    private function deleteUnsharedImage(?string $path): void
    {
        if ($path && ! Product::where('image', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products')->ignore($product?->id)],
            'price' => ['required', 'numeric', 'decimal:0,2', 'between:0,9999999999.99'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'stock' => ['required', 'integer', 'between:0,4294967295'],
            'details' => ['sometimes', 'array', 'max:50'], 'details.*' => ['required', 'string', 'max:1000'],
            'rating' => ['sometimes', 'numeric', 'decimal:0,1', 'between:0,5'],
            'sold' => ['sometimes', 'integer', 'between:0,4294967295'],
            'description' => ['nullable', 'string', 'max:10000'],
            'image' => ['sometimes', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
        if ($request->isMethod('patch')) {
            foreach (['name', 'slug', 'price', 'category_id', 'stock'] as $field) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        return $request->validate($rules);
    }
}
