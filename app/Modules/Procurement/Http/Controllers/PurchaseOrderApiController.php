<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Requests\PurchaseOrderRequest;
use App\Modules\Procurement\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Http\Request;

/** API đơn mua hàng: GET/POST /api/v1/procurement/purchase-orders ... */
class PurchaseOrderApiController extends Controller
{
    use ListsResources;

    public function __construct(
        protected PurchaseOrderService $purchaseOrderService,
    ) {}

    public function index(Request $request)
    {
        $query = PurchaseOrder::query()->with(['supplier']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        $this->applyFilters($query, $request, ['status']);
        $this->applySort($query, $request, ['code', 'total_amount', 'order_date', 'created_at']);

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json($purchaseOrder->load(['supplier', 'lines.product']));
    }

    public function store(PurchaseOrderRequest $request)
    {
        $order = $this->purchaseOrderService->create($request->validated(), CurrentCompany::id());

        return response()->json($order->load('lines'), 201);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        try {
            $order = $this->purchaseOrderService->approve($purchaseOrder->id, $request->input('note'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($order);
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        try {
            $order = $this->purchaseOrderService->cancel($purchaseOrder->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($order);
    }
}
