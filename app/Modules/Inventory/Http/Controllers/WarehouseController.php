<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\WarehouseRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý kho hàng (Inertia). */
class WarehouseController extends Controller
{
    use ListsResources;

    public function index(Request $request)
    {
        $query = Warehouse::query()->withCount('locations');

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        $this->applyFilters($query, $request, ['is_active']);
        $this->applySort($query, $request, ['code', 'name', 'created_at']);

        return Inertia::render('Inventory/Warehouses/Index', [
            'warehouses' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Inventory/Warehouses/Create');
    }

    public function store(WarehouseRequest $request)
    {
        Warehouse::create($request->validated());

        return redirect()->route('inventory.warehouses.index')->with('success', 'Đã tạo kho mới.');
    }

    public function edit(Warehouse $warehouse)
    {
        return Inertia::render('Inventory/Warehouses/Edit', ['warehouse' => $warehouse]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());

        return redirect()->route('inventory.warehouses.index')->with('success', 'Đã cập nhật kho.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return redirect()->route('inventory.warehouses.index')->with('success', 'Đã xóa kho.');
    }
}
