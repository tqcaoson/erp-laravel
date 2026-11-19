<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\ManufacturingOrder;
use App\Modules\Manufacturing\Models\Routing;
use App\Modules\Manufacturing\Requests\CompleteManufacturingOrderRequest;
use App\Modules\Manufacturing\Requests\LogManufacturingProgressRequest;
use App\Modules\Manufacturing\Requests\StoreManufacturingOrderRequest;
use App\Modules\Manufacturing\Services\ManufacturingOrderService;
use App\Modules\Manufacturing\Support\CatalogLookup;
use App\Modules\Sales\Models\SalesOrder;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý lệnh sản xuất (Inertia). */
class ManufacturingOrderController extends Controller
{
    use CatalogLookup;

    public function __construct(protected ManufacturingOrderService $manufacturingOrders) {}

    public function index(Request $request)
    {
        $query = ManufacturingOrder::query();

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $products = $this->productMap($orders->getCollection()->pluck('product_id')->unique()->all());
        $warehouses = $this->warehouseMap($orders->getCollection()->pluck('warehouse_id')->filter()->unique()->all());
        $orders->getCollection()->each(function (ManufacturingOrder $order) use ($products, $warehouses) {
            $order->product_name = $products[$order->product_id]['name'] ?? ('Sản phẩm #'.$order->product_id);
            $order->warehouse_name = $order->warehouse_id ? ($warehouses[$order->warehouse_id] ?? ('Kho #'.$order->warehouse_id)) : null;
        });

        return Inertia::render('Manufacturing/ManufacturingOrders/Index', [
            'orders' => $orders,
            'statuses' => ManufacturingOrder::STATUS_LABELS,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Manufacturing/ManufacturingOrders/Create', [
            'products' => $this->productList(),
            'warehouses' => $this->warehouseList(),
            'boms' => Bom::with('lines')->where('is_active', true)->orderBy('code')->get(),
            'routings' => Routing::orderBy('name')->get(['id', 'code', 'name', 'product_id']),
            'salesOrders' => SalesOrder::whereIn('status', ['confirmed', 'partially_delivered'])
                ->orderByDesc('order_date')->limit(50)->get(['id', 'code']),
        ]);
    }

    public function store(StoreManufacturingOrderRequest $request)
    {
        $order = $this->manufacturingOrders->create($request->validated());

        return redirect()->route('manufacturing.orders.show', $order)->with('success', "Đã tạo lệnh sản xuất {$order->code}.");
    }

    public function show(ManufacturingOrder $order)
    {
        $order->load(['bom.lines.unit', 'routing.operations.workCenter', 'workOrders.workCenter', 'productionLogs']);
        $productIds = collect([$order->product_id])
            ->merge($order->bom?->lines->pluck('component_product_id') ?? [])
            ->unique()->all();
        $products = $this->productMap($productIds);
        $order->product_name = $products[$order->product_id]['name'] ?? ('Sản phẩm #'.$order->product_id);
        $order->bom?->lines->each(function ($line) use ($products) {
            $line->component_name = $products[$line->component_product_id]['name'] ?? ('Sản phẩm #'.$line->component_product_id);
        });
        $warehouses = $this->warehouseMap($order->warehouse_id ? [$order->warehouse_id] : []);
        $order->warehouse_name = $order->warehouse_id ? ($warehouses[$order->warehouse_id] ?? ('Kho #'.$order->warehouse_id)) : null;

        // Nhu cầu nguyên liệu theo BOM (để hiển thị trên trang chi tiết).
        $required = $this->manufacturingOrders->requiredMaterials($order);
        $requiredNames = $this->productMap(array_keys($required));
        $materials = collect($required)->map(fn ($qty, $productId) => [
            'product_id' => $productId,
            'name' => $requiredNames[$productId]['name'] ?? ('Sản phẩm #'.$productId),
            'qty' => $qty,
        ])->values()->all();

        return Inertia::render('Manufacturing/ManufacturingOrders/Show', [
            'order' => $order,
            'materials' => $materials,
        ]);
    }

    public function confirm(ManufacturingOrder $order)
    {
        try {
            $this->manufacturingOrders->confirm($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã xác nhận lệnh sản xuất.');
    }

    public function start(ManufacturingOrder $order)
    {
        try {
            $this->manufacturingOrders->start($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã bắt đầu sản xuất.');
    }

    /** Ghi nhận tiến độ từng phần (không đổi trạng thái lệnh). */
    public function logProgress(LogManufacturingProgressRequest $request, ManufacturingOrder $order)
    {
        try {
            $result = $this->manufacturingOrders->logProgress(
                $order,
                (float) $request->validated()['qty_produced'],
                (float) ($request->validated()['qty_scrap'] ?? 0),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = 'Đã ghi nhận tiến độ: thêm '.number_format((float) $request->validated()['qty_produced'], 2, ',', '.')
            .' thành phẩm (tổng đã sản xuất '.number_format((float) $result['order']->produced_qty, 2, ',', '.')
            .' / '.number_format((float) $order->qty, 2, ',', '.').').';
        if ($result['warnings'] !== []) {
            $message .= ' Lưu ý: '.implode(' ', $result['warnings']);
        }

        return back()->with('success', $message);
    }

    public function complete(CompleteManufacturingOrderRequest $request, ManufacturingOrder $order)
    {
        try {
            $result = $this->manufacturingOrders->complete(
                $order,
                (float) $request->validated()['qty_produced'],
                (float) ($request->validated()['qty_scrap'] ?? 0),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = 'Đã hoàn thành sản xuất. Giá vốn thành phẩm: '.number_format($result['unit_cost'], 0, ',', '.').' ₫/đơn vị.';
        if ($result['warnings'] !== []) {
            $message .= ' Lưu ý: '.implode(' ', $result['warnings']);
        }

        return back()->with('success', $message);
    }

    public function cancel(ManufacturingOrder $order)
    {
        try {
            $this->manufacturingOrders->cancel($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hủy lệnh sản xuất.');
    }
}
