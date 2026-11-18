<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockMove;
use App\Modules\Inventory\Models\StockQuant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\StockMoveRequest;
use App\Modules\Inventory\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web tồn kho + phiếu nhập/xuất/chuyển kho + sổ kho (Inertia). */
class StockController extends Controller
{
    use ListsResources;

    public function __construct(
        protected StockService $stockService,
    ) {}

    /** Tồn kho theo kho/sản phẩm, kèm cảnh báo dưới mức tối thiểu. */
    public function index(Request $request)
    {
        $query = StockQuant::query()->with(['product.uom', 'warehouse', 'location', 'lot']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereHas('product', fn ($q) => $q
                ->whereColumn('products.reorder_min', '>', 'stock_quants.qty'));
        }

        $this->applySort($query, $request, ['qty', 'created_at']);

        $quants = $query->paginate($this->perPage($request))->withQueryString();

        // Thêm tồn khả dụng + cờ dưới mức tối thiểu cho từng dòng.
        $quants->getCollection()->transform(function (StockQuant $quant) {
            $quant->available_qty = $quant->availableQty();
            $quant->is_below_minimum = (float) $quant->product->reorder_min > 0
                && $quant->availableQty() < (float) $quant->product->reorder_min;

            return $quant;
        });

        return Inertia::render('Inventory/Stock/Index', [
            'quants' => $quants,
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'filters' => $request->only(['warehouse_id', 'product_id', 'low_stock', 'sort']),
        ]);
    }

    /** Form phiếu nhập/xuất/chuyển kho. */
    public function moveForm()
    {
        return Inertia::render('Inventory/Stock/Move', [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'average_cost']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'warehouse_id', 'code', 'name']),
        ]);
    }

    /** Ghi phiếu nhập/xuất/chuyển kho qua StockService. */
    public function move(StockMoveRequest $request)
    {
        $data = $request->validated();

        try {
            // Phiếu nhập/xuất tay: nguồn là thao tác thủ công của người dùng
            // hiện tại (source_type 'manual', source_id = id người dùng).
            $sourceType = 'manual';
            $sourceId = (int) auth()->id();

            match ($data['type']) {
                'receive' => $this->stockService->receive(
                    $data['product_id'], $data['warehouse_id'], $data['location_id'] ?? null,
                    (float) $data['qty'], (float) ($data['unit_cost'] ?? 0), $sourceType, $sourceId, $data['lot_id'] ?? null,
                ),
                'issue' => $this->stockService->issue(
                    $data['product_id'], $data['warehouse_id'], $data['location_id'] ?? null,
                    (float) $data['qty'], $sourceType, $sourceId, $data['lot_id'] ?? null,
                ),
                'transfer' => $this->stockService->transfer(
                    $data['product_id'], $data['warehouse_id'], $data['to_warehouse_id'],
                    $data['location_id'] ?? null, $data['to_location_id'] ?? null,
                    (float) $data['qty'], $sourceType, $sourceId,
                ),
            };
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('inventory.stock.index')->with('success', 'Đã ghi phiếu kho.');
    }

    /** Sổ kho (lịch sử nhập/xuất). */
    public function moves(Request $request)
    {
        $query = StockMove::query()->with(['product', 'warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', 'like', '%'.$request->input('source_type').'%');
        }

        $this->applySort($query, $request, ['moved_at', 'qty', 'created_at'], '-moved_at');

        return Inertia::render('Inventory/Stock/Moves', [
            'moves' => $query->paginate($this->perPage($request))->withQueryString(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'filters' => $request->only(['warehouse_id', 'product_id', 'source_type', 'sort']),
        ]);
    }
}
