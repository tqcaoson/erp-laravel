<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Requests\StoreSalesOrderRequest;
use App\Modules\Sales\Requests\UpdateSalesOrderRequest;
use App\Modules\Sales\Services\DeliveryOrderService;
use App\Modules\Sales\Services\SalesOrderService;
use App\Modules\Sales\Support\CatalogLookup;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý đơn bán hàng (Inertia). */
class SalesOrderController extends Controller
{
    use CatalogLookup;

    public function __construct(
        protected SalesOrderService $salesOrders,
        protected DeliveryOrderService $deliveryOrders,
    ) {}

    public function index(Request $request)
    {
        $query = SalesOrder::query()->with('customer:id,name');

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        if ($request->filled('filter.customer_id')) {
            $query->where('customer_id', $request->input('filter.customer_id'));
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $warehouses = $this->warehouseMap($orders->getCollection()->pluck('warehouse_id')->unique()->all());
        $orders->getCollection()->each(function (SalesOrder $order) use ($warehouses) {
            $order->warehouse_name = $warehouses[$order->warehouse_id] ?? ('Kho #'.$order->warehouse_id);
        });

        return Inertia::render('Sales/SalesOrders/Index', [
            'orders' => $orders,
            'statuses' => SalesOrder::STATUS_LABELS,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        $warehouses = $this->warehouseList();
        $products = $this->productList();

        // Tồn khả dụng tại kho mặc định (kho đầu tiên) để hiển thị ngay trên form;
        // khi đổi kho, trang sẽ gọi API /api/v1/sales/stock-availability để tải lại.
        $defaultWarehouseId = $warehouses[0]->id ?? null;
        $stockMap = $defaultWarehouseId
            ? $this->stockMap((int) $defaultWarehouseId, collect($products)->pluck('id')->all())
            : [];

        // Hạn mức công nợ còn lại của từng khách (để hiển thị khi chọn khách).
        $customers = $this->customersWithCredit();

        return Inertia::render('Sales/SalesOrders/Create', [
            'customers' => $customers,
            'warehouses' => $warehouses,
            'products' => $products,
            'stockMap' => $stockMap,
        ]);
    }

    public function store(StoreSalesOrderRequest $request)
    {
        $order = $this->salesOrders->create($request->validated());

        return redirect()->route('sales.orders.show', $order)->with('success', "Đã tạo đơn bán {$order->code}.");
    }

    /** Form sửa đơn bán — chỉ đơn nháp mới sửa được. */
    public function edit(SalesOrder $order)
    {
        if ($order->status !== 'draft') {
            return redirect()->route('sales.orders.show', $order)
                ->with('error', 'Chỉ sửa được đơn bán ở trạng thái nháp.');
        }

        $order->load('lines');
        $warehouses = $this->warehouseList();
        $products = $this->productList();
        $stockMap = $this->stockMap((int) $order->warehouse_id, collect($products)->pluck('id')->all());

        return Inertia::render('Sales/SalesOrders/Edit', [
            'order' => $order,
            'customers' => $this->customersWithCredit(),
            'warehouses' => $warehouses,
            'products' => $products,
            'stockMap' => $stockMap,
        ]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $order)
    {
        try {
            $order = $this->salesOrders->update($order, $request->validated());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.orders.show', $order)->with('success', "Đã cập nhật đơn bán {$order->code}.");
    }

    public function show(SalesOrder $order)
    {
        $order->load(['customer', 'quotation', 'lines', 'deliveryOrders']);
        $order->customer->current_debt = $order->customer->currentDebt();
        $order->customer->remaining_credit = $order->customer->remainingCredit();

        $products = $this->productMap($order->lines->pluck('product_id')->all());
        $order->lines->each(function ($line) use ($products) {
            $line->product_name = $products[$line->product_id]['name'] ?? ('Sản phẩm #'.$line->product_id);
            $line->product_sku = $products[$line->product_id]['sku'] ?? null;
        });

        $warehouses = $this->warehouseMap([$order->warehouse_id]);
        $order->warehouse_name = $warehouses[$order->warehouse_id] ?? ('Kho #'.$order->warehouse_id);

        return Inertia::render('Sales/SalesOrders/Show', [
            'order' => $order,
            'warnings' => $order->status === 'draft' ? $this->salesOrders->checkStockAvailability($order) : [],
        ]);
    }

    /**
     * Tồn khả dụng (JSON) phục vụ form tạo đơn khi đổi kho.
     * GET /sales/stock-availability?warehouse_id=1&product_ids[]=2
     */
    public function stockAvailability(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer'],
        ]);

        return response()->json([
            'data' => $this->stockMap((int) $validated['warehouse_id'], $validated['product_ids']),
        ]);
    }

    public function confirm(SalesOrder $order)
    {
        try {
            $result = $this->salesOrders->confirm($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $result['message'];
        if ($result['warnings'] !== []) {
            $message .= ' Cảnh báo tồn kho: '.implode(' ', $result['warnings']);
        }

        return back()->with('success', $message);
    }

    public function approve(SalesOrder $order)
    {
        try {
            $result = $this->salesOrders->approve($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result['message']);
    }

    public function cancel(SalesOrder $order)
    {
        try {
            $this->salesOrders->cancel($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hủy đơn bán.');
    }

    /** Tạo phiếu giao nháp cho phần còn thiếu của đơn (giao nốt). */
    public function deliverRemaining(SalesOrder $order)
    {
        try {
            $result = $this->deliveryOrders->createForRemaining($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = "Đã tạo phiếu giao {$result['order']->code} cho phần còn lại của đơn bán.";
        if ($result['warnings'] !== []) {
            $message .= ' Lưu ý: '.implode(' ', $result['warnings']);
        }

        return redirect()->route('sales.deliveries.show', $result['order'])->with('success', $message);
    }

    /** Lập hóa đơn nháp từ phần đã giao chưa lập hóa đơn của đơn bán. */
    public function createInvoice(SalesOrder $order)
    {
        try {
            $invoiceId = $this->salesOrders->createInvoiceFromDelivered($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.invoices.show', $invoiceId)
            ->with('success', "Đã lập hóa đơn nháp từ phần đã giao của đơn bán {$order->code}.");
    }

    /** Danh sách khách kèm hạn mức/công nợ còn lại (hiển thị trên form đơn). */
    protected function customersWithCredit()
    {
        return Customer::orderBy('name')->get()->map(function (Customer $customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'credit_limit' => (float) $customer->credit_limit,
                'current_debt' => $customer->currentDebt(),
                'remaining_credit' => $customer->remainingCredit(),
            ];
        });
    }
}
