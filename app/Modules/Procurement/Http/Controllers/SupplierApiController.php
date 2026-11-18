<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Requests\SupplierRequest;
use Illuminate\Http\Request;

/** API nhà cung cấp: GET /api/v1/procurement/suppliers ... */
class SupplierApiController extends Controller
{
    use ListsResources;

    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        $this->applyFilters($query, $request, ['is_active']);
        $this->applySort($query, $request, ['code', 'name', 'created_at']);

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function show(Supplier $supplier)
    {
        return response()->json($supplier);
    }

    public function store(SupplierRequest $request)
    {
        return response()->json(Supplier::create($request->validated()), 201);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->json(['message' => 'Đã xóa nhà cung cấp.']);
    }
}
