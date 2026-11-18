<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Requests\PurchaseOrderRequest;
use App\Modules\Procurement\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web đơn mua hàng (Inertia). */
class PurchaseOrderController extends Controller
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

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'orders' => $query->paginate($this->perPage($request))->withQueryString(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => $request->only(['supplier_id', 'sort', 'filter']),
        ]);
    }

    public function create(Request $request)
    {
        // Tạo PO từ PR đã duyệt: tự điền dòng từ PR.
        $purchaseRequest = $request->filled('purchase_request_id')
            ? PurchaseRequest::query()->with('lines.product')->find($request->input('purchase_request_id'))
            : null;

        return Inertia::render('Procurement/PurchaseOrders/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'average_cost']),
            'purchaseRequests' => PurchaseRequest::query()->where('status', 'approved')->orderByDesc('created_at')->get(['id', 'code', 'total_amount']),
            'fromRequest' => $purchaseRequest,
        ]);
    }

    public function store(PurchaseOrderRequest $request)
    {
        $order = $this->purchaseOrderService->create($request->validated(), CurrentCompany::id());

        return redirect()->route('procurement.purchase-orders.show', $order)
            ->with('success', 'Đã tạo đơn mua '.$order->code.'.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'order' => $purchaseOrder->load(['supplier', 'purchaseRequest', 'lines.product', 'goodsReceipts']),
        ]);
    }

    public function submit(PurchaseOrder $purchaseOrder)
    {
        try {
            $this->purchaseOrderService->submit($purchaseOrder->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã trình duyệt đơn mua hàng.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        try {
            $this->purchaseOrderService->approve($purchaseOrder->id, $request->input('note'));
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã duyệt đơn mua hàng.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        try {
            $this->purchaseOrderService->cancel($purchaseOrder->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã hủy đơn mua hàng.');
    }

    public function close(PurchaseOrder $purchaseOrder)
    {
        try {
            $this->purchaseOrderService->close($purchaseOrder->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã đóng đơn mua hàng.');
    }
}
