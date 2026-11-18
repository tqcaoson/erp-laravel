<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Requests\ProductRequest;
use Illuminate\Http\Request;

/** API sản phẩm: GET /api/v1/inventory/products ... */
class ProductApiController extends Controller
{
    use ListsResources;

    public function index(Request $request)
    {
        $query = Product::query()->with('uom');

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        $this->applyFilters($query, $request, ['is_active', 'cost_method']);
        $this->applySort($query, $request, ['sku', 'name', 'sale_price', 'created_at']);

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['uom', 'units.unit', 'lots']));
    }

    public function store(ProductRequest $request)
    {
        return response()->json(Product::create($request->validated()), 201);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Đã xóa sản phẩm.']);
    }
}
