<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Requests\SupplierRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý nhà cung cấp (Inertia). */
class SupplierController extends Controller
{
    use ListsResources;

    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('tax_code', 'like', "%{$search}%"));
        }

        $this->applyFilters($query, $request, ['is_active']);
        $this->applySort($query, $request, ['code', 'name', 'created_at']);

        return Inertia::render('Procurement/Suppliers/Index', [
            'suppliers' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Procurement/Suppliers/Create');
    }

    public function store(SupplierRequest $request)
    {
        Supplier::create($request->validated());

        return redirect()->route('procurement.suppliers.index')->with('success', 'Đã tạo nhà cung cấp mới.');
    }

    public function edit(Supplier $supplier)
    {
        return Inertia::render('Procurement/Suppliers/Edit', ['supplier' => $supplier]);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return redirect()->route('procurement.suppliers.index')->with('success', 'Đã cập nhật nhà cung cấp.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('procurement.suppliers.index')->with('success', 'Đã xóa nhà cung cấp.');
    }
}
