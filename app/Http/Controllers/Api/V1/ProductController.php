<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $products = Product::query()
            ->where('is_active', true)
            ->with(['category', 'unit'])
            ->withSum(['stocks as stock_qty' => fn ($query) => $query->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))], 'quantity')
            ->when($request->query('search'), fn ($query, string $term) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")))
            ->when($request->query('category_id'), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 30));

        return ProductResource::collection($products);
    }

    /** Exact lookup by barcode or SKU (for the scanner). */
    public function byBarcode(string $code): ProductResource
    {
        $product = Product::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'unit']));
    }
}
