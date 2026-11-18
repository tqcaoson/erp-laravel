<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\DeliveryOrder;
use App\Modules\Sales\Services\DeliveryOrderService;
use App\Modules\Sales\Support\CatalogLookup;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý phiếu giao hàng (Inertia). */
class DeliveryOrderController extends Controller
{
    use CatalogLookup;

    public function __construct(protected DeliveryOrderService $deliveryOrders) {}

    public function index(Request $request)
    {
        $query = DeliveryOrder::query()->with('salesOrder:id,code,customer_id');

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $warehouses = $this->warehouseMap($orders->getCollection()->pluck('warehouse_id')->unique()->all());
        $orders->getCollection()->each(function (DeliveryOrder $order) use ($warehouses) {
            $order->warehouse_name = $warehouses[$order->warehouse_id] ?? ('Kho #'.$order->warehouse_id);
        });

        return Inertia::render('Sales/DeliveryOrders/Index', [
            'deliveryOrders' => $orders,
            'statuses' => DeliveryOrder::STATUS_LABELS,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load(['salesOrder.customer', 'lines']);

        $products = $this->productMap($deliveryOrder->lines->pluck('product_id')->all());
        $deliveryOrder->lines->each(function ($line) use ($products) {
            $line->product_name = $products[$line->product_id]['name'] ?? ('Sản phẩm #'.$line->product_id);
            $line->product_sku = $products[$line->product_id]['sku'] ?? null;
        });

        $warehouses = $this->warehouseMap([$deliveryOrder->warehouse_id]);
        $deliveryOrder->warehouse_name = $warehouses[$deliveryOrder->warehouse_id] ?? ('Kho #'.$deliveryOrder->warehouse_id);

        return Inertia::render('Sales/DeliveryOrders/Show', ['deliveryOrder' => $deliveryOrder]);
    }

    /** Xác nhận giao hàng: xuất kho thật + phát event DeliveryOrderValidated. */
    public function validate(DeliveryOrder $deliveryOrder)
    {
        try {
            $result = $this->deliveryOrders->validate($deliveryOrder);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = 'Đã xác nhận giao hàng.';
        if ($result['warnings'] !== []) {
            $message .= ' Lưu ý: '.implode(' ', $result['warnings']);
        }

        return back()->with('success', $message);
    }

    public function cancel(DeliveryOrder $deliveryOrder)
    {
        try {
            $this->deliveryOrders->cancel($deliveryOrder);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hủy phiếu giao hàng.');
    }
}
