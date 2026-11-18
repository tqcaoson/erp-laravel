<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\Contracts\StockServiceInterface;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Requests\StoreSalesOrderRequest;
use App\Modules\Sales\Services\SalesOrderService;
use DomainException;
use Illuminate\Http\Request;

/**
 * REST API đơn bán hàng — đúng ví dụ mục 9.1 thiết kế:
 * GET /api/v1/sales/orders?filter[status]=confirmed&per_page=20
 * POST /api/v1/sales/orders, GET /api/v1/sales/orders/{id},
 * POST /api/v1/sales/orders/{id}/confirm, POST /api/v1/sales/orders/{id}/cancel.
 * Lỗi trạng thái trả 409 với message tiếng Việt.
 */
class SalesOrderApiController extends Controller
{
    public function __construct(protected SalesOrderService $salesOrders) {}

    public function index(Request $request)
    {
        $query = SalesOrder::query()->with('customer:id,name');

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        if ($request->filled('filter.customer_id')) {
            $query->where('customer_id', $request->input('filter.customer_id'));
        }

        $this->applySort($query, $request);

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(StoreSalesOrderRequest $request)
    {
        $order = $this->salesOrders->create($request->validated());

        return response()->json($order->load(['customer:id,name', 'lines']), 201);
    }

    public function show(SalesOrder $order)
    {
        return response()->json($order->load(['customer', 'lines', 'deliveryOrders']));
    }

    public function confirm(SalesOrder $order)
    {
        try {
            $result = $this->salesOrders->confirm($order);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $result['order'],
            'needs_approval' => $result['needs_approval'],
            'warnings' => $result['warnings'],
            'message' => $result['message'],
        ]);
    }

    public function approve(SalesOrder $order)
    {
        try {
            $result = $this->salesOrders->approve($order);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $result['order'],
            'warnings' => $result['warnings'],
            'message' => $result['message'],
        ]);
    }

    public function cancel(SalesOrder $order)
    {
        try {
            $order = $this->salesOrders->cancel($order);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $order, 'message' => 'Đã hủy đơn bán.']);
    }

    /**
     * Tồn khả dụng phục vụ form tạo đơn (khi đổi kho):
     * GET /api/v1/sales/stock-availability?warehouse_id=1&product_ids[]=2&product_ids[]=3
     * Trả về rỗng khi StockService chưa khả dụng — giao diện sẽ hiển thị "—".
     */
    public function stockAvailability(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer'],
        ]);

        $result = [];
        try {
            if (app()->bound(StockServiceInterface::class)) {
                $stock = app(StockServiceInterface::class);
                foreach ($validated['product_ids'] as $productId) {
                    $result[(int) $productId] = $stock->availableQty((int) $productId, (int) $validated['warehouse_id']);
                }
            }
        } catch (\Throwable) {
            $result = [];
        }

        return response()->json(['data' => $result]);
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }

    /** Sắp xếp theo chuẩn API: sort=-created_at (giảm dần) hoặc sort=code (tăng dần). */
    protected function applySort($query, Request $request): void
    {
        $sort = (string) $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, ['created_at', 'order_date', 'code', 'total_amount', 'status'], true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        $query->orderBy($column, $direction);
    }
}
