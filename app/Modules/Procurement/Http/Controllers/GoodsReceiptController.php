<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Requests\GoodsReceiptRequest;
use App\Modules\Procurement\Services\GoodsReceiptService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web phiếu nhận hàng (Inertia). */
class GoodsReceiptController extends Controller
{
    use ListsResources;

    public function __construct(
        protected GoodsReceiptService $goodsReceiptService,
    ) {}

    public function index(Request $request)
    {
        $query = GoodsReceipt::query()->with(['supplier', 'warehouse', 'purchaseOrder']);

        $this->applyFilters($query, $request, ['status']);
        $this->applySort($query, $request, ['code', 'receipt_date', 'created_at']);

        return Inertia::render('Procurement/GoodsReceipts/Index', [
            'receipts' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['sort', 'filter']),
        ]);
    }

    public function create(Request $request)
    {
        // Nhận hàng từ PO: tự điền dòng còn thiếu từ PO.
        $purchaseOrder = $request->filled('purchase_order_id')
            ? PurchaseOrder::query()->with('lines.product')->find($request->input('purchase_order_id'))
            : null;

        return Inertia::render('Procurement/GoodsReceipts/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'average_cost']),
            'purchaseOrders' => PurchaseOrder::query()->whereIn('status', ['approved', 'partially_received'])->orderByDesc('created_at')->get(['id', 'code', 'supplier_id', 'total_amount']),
            'fromOrder' => $purchaseOrder,
        ]);
    }

    public function store(GoodsReceiptRequest $request)
    {
        $receipt = $this->goodsReceiptService->create($request->validated(), CurrentCompany::id());

        return redirect()->route('procurement.goods-receipts.show', $receipt)
            ->with('success', 'Đã tạo phiếu nhận hàng '.$receipt->code.'.');
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        return Inertia::render('Procurement/GoodsReceipts/Show', [
            'receipt' => $goodsReceipt->load(['supplier', 'warehouse', 'purchaseOrder', 'lines.product']),
        ]);
    }

    /** Xác nhận phiếu nhận: đối chiếu PO, cập nhật PO, tăng tồn kho. */
    public function validateReceipt(GoodsReceipt $goodsReceipt)
    {
        try {
            $this->goodsReceiptService->validate($goodsReceipt->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã xác nhận phiếu nhận hàng, tồn kho đã được cập nhật.');
    }

    public function cancel(GoodsReceipt $goodsReceipt)
    {
        try {
            $this->goodsReceiptService->cancel($goodsReceipt->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã hủy phiếu nhận hàng.');
    }
}
