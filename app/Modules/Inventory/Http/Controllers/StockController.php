<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockMove;
use App\Modules\Inventory\Models\StockMoveRequest as StockMoveRequestModel;
use App\Modules\Inventory\Models\StockQuant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\StockMoveRequest;
use App\Modules\Inventory\Services\StockService;
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

    /**
     * Tạo phiếu nhập/xuất/chuyển kho chờ duyệt.
     * Tồn kho KHÔNG đổi ở bước này — chỉ khi người có quyền duyệt
     * (inventory.stock.confirm) duyệt phiếu thì StockService mới ghi tồn.
     */
    public function move(StockMoveRequest $request)
    {
        $data = $request->validated();

        StockMoveRequestModel::create([
            'type' => $data['type'],
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'] ?? null,
            'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
            'to_location_id' => $data['to_location_id'] ?? null,
            'lot_id' => $data['lot_id'] ?? null,
            'qty' => $data['qty'],
            'unit_cost' => $data['unit_cost'] ?? null,
            'status' => 'pending',
            'requested_by' => auth()->id(),
        ]);

        return redirect()->route('inventory.stock.index')->with('success', 'Đã gửi phiếu chờ duyệt.');
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
