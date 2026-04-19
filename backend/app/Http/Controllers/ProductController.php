<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Get all products with filters, search, and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Start eloquent builder with relationships
        $query = Product::with(['category', 'preorder']);

        // 1. Search Query
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // 2. Filter by Category / Brand
        if ($request->filled('brand')) {
            $brandList = explode(',', $request->input('brand'));
            $query->whereIn('brand', $brandList);
        }

        if ($request->filled('scale')) {
            $scaleList = explode(',', $request->input('scale'));
            $query->whereIn('scale', $scaleList);
        }

        // 3. Filter by Price Range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        // 4. Filter by Limited Edition
        if ($request->filled('is_limited_edition')) {
            $query->where('is_limited_edition', $request->boolean('is_limited_edition'));
        }

        // 5. Filter by Availability Status
        if ($request->filled('availability')) {
            $availability = $request->input('availability');
            if ($availability === 'in_stock') {
                $query->where('stock', '>', 0);
            } elseif ($availability === 'preorder') {
                $query->where('is_preorder', true);
            }
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate (default 12 items per page for a nice grid)
        $perPage = $request->input('per_page', 12);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Get single product details.
     */
    public function show($id): ProductResource|JsonResponse
    {
        $product = Product::with(['category', 'preorder'])->find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ], 404);
        }

        return new ProductResource($product);
    }

    /**
     * Extract filter data like available brands and scales for the frontend sidebar.
     */
    public function filters(): JsonResponse
    {
        $brands = Product::whereNotNull('brand')->distinct()->pluck('brand');
        $scales = Product::whereNotNull('scale')->distinct()->pluck('scale');
        
        $maxPrice = Product::max('price');
        $minPrice = Product::min('price');

        return response()->json([
            'status' => 'success',
            'data' => [
                'brands' => $brands,
                'scales' => $scales,
                'price_range' => [
                    'min' => $minPrice,
                    'max' => $maxPrice,
                ]
            ]
        ]);
    }
}
