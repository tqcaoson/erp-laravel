<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockMove;
use App\Modules\Inventory\Models\StockQuant;
use App\Modules\Inventory\Requests\StockMoveRequest;
use App\Modules\Inventory\Services\StockService;
use DomainException;
use Illuminate\Http\Request;

/**
 * API tồn kho + phiếu kho:
 * - GET /api/v1/inventory/stock?warehouse_id=2&product_id=15 (vd mục 9.1)
 * - GET /api/v1/inventory/stock-moves
 * - POST /api/v1/inventory/stock-moves {type: receive|issue|transfer, ...}
 */
class StockApiController extends Controller
{
    use ListsResources;

    public function __construct(
        protected StockService $stockService,
    ) {}

    public function index(Request $request)
    {
        $query = StockQuant::query()->with(['product.uom', 'warehouse', 'location', 'lot']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $this->applySort($query, $request, ['qty', 'created_at']);

        $paginator = $query->paginate($this->perPage($request));

        $paginator->getCollection()->transform(function (StockQuant $quant) {
            $quant->available_qty = $quant->availableQty();

            return $quant;
        });

        return response()->json($paginator);
    }

    public function moves(Request $request)
    {
        $query = StockMove::query()->with(['product', 'warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $this->applySort($query, $request, ['moved_at', 'qty', 'created_at'], '-moved_at');

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function storeMove(StockMoveRequest $request)
    {
        $data = $request->validated();

        try {
            $sourceType = 'manual';
            $sourceId = (int) auth()->id();

            $moveId = match ($data['type']) {
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
            // Thiếu tồn / trạng thái không hợp lệ → 409 theo chuẩn API mục 9.1.
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(StockMove::find($moveId), 201);
    }
}
