<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Models\Unit;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Requests\ProductRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý sản phẩm (Inertia). */
class ProductController extends Controller
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

        $this->applyFilters($query, $request, ['is_active']);
        $this->applySort($query, $request, ['sku', 'name', 'sale_price', 'created_at']);

        return Inertia::render('Inventory/Products/Index', [
            'products' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Inventory/Products/Create', [
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(ProductRequest $request)
    {
        Product::create($request->validated());

        return redirect()->route('inventory.products.index')->with('success', 'Đã tạo sản phẩm mới.');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Inventory/Products/Edit', [
            'product' => $product->load('uom'),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return redirect()->route('inventory.products.index')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('inventory.products.index')->with('success', 'Đã xóa sản phẩm.');
    }
}
