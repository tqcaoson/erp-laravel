<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\StockCountRequest;
use App\Modules\Inventory\Services\StockCountService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web kiểm kê kho (Inertia). */
class StockCountController extends Controller
{
    use ListsResources;

    public function __construct(
        protected StockCountService $stockCountService,
    ) {}

    public function index(Request $request)
    {
        $query = StockCount::query()->with(['warehouse'])->withCount('lines');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        $this->applyFilters($query, $request, ['status']);
        $this->applySort($query, $request, ['code', 'created_at']);

        return Inertia::render('Inventory/StockCounts/Index', [
            'counts' => $query->paginate($this->perPage($request))->withQueryString(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => $request->only(['warehouse_id', 'sort', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Inventory/StockCounts/Create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }

    public function store(StockCountRequest $request)
    {
        $data = $request->validated();

        $count = $this->stockCountService->create(
            CurrentCompany::id(),
            $data['warehouse_id'],
            $data['product_ids'] ?? [],
            $data['note'] ?? null,
        );

        return redirect()->route('inventory.stock-counts.show', $count)->with('success', 'Đã tạo phiếu kiểm kê '.$count->code.'.');
    }

    public function show(StockCount $stockCount)
    {
        return Inertia::render('Inventory/StockCounts/Show', [
            'count' => $stockCount->load(['warehouse', 'lines.product.uom']),
        ]);
    }

    /** Nhập số thực tế cho các dòng kiểm kê. */
    public function updateCounted(StockCountRequest $request, StockCount $stockCount)
    {
        try {
            $this->stockCountService->updateCounted($stockCount->id, $request->validated()['lines']);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã lưu số thực tế.');
    }

    /** Hoàn tất kiểm kê → sinh phiếu điều chỉnh chênh lệch. */
    public function complete(StockCount $stockCount)
    {
        try {
            $this->stockCountService->complete($stockCount->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã hoàn tất kiểm kê, chênh lệch đã được điều chỉnh vào tồn kho.');
    }

    public function cancel(StockCount $stockCount)
    {
        try {
            $this->stockCountService->cancel($stockCount->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã hủy phiếu kiểm kê.');
    }
}
