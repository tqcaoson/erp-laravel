<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\ManufacturingOrder;
use App\Modules\Manufacturing\Requests\CompleteManufacturingOrderRequest;
use App\Modules\Manufacturing\Requests\StoreManufacturingOrderRequest;
use App\Modules\Manufacturing\Services\ManufacturingOrderService;
use DomainException;
use Illuminate\Http\Request;

/**
 * REST API lệnh sản xuất: danh sách, tạo, xem, bắt đầu, hoàn thành.
 * Lỗi trạng thái trả 409 với message tiếng Việt.
 */
class ManufacturingOrderApiController extends Controller
{
    public function __construct(protected ManufacturingOrderService $manufacturingOrders) {}

    public function index(Request $request)
    {
        $query = ManufacturingOrder::query();

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    public function store(StoreManufacturingOrderRequest $request)
    {
        $order = $this->manufacturingOrders->create($request->validated());

        return response()->json($order, 201);
    }

    public function show(ManufacturingOrder $order)
    {
        return response()->json($order->load(['bom.lines', 'workOrders', 'productionLogs']));
    }

    public function confirm(ManufacturingOrder $order)
    {
        try {
            $order = $this->manufacturingOrders->confirm($order);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $order, 'message' => 'Đã xác nhận lệnh sản xuất.']);
    }

    public function start(ManufacturingOrder $order)
    {
        try {
            $order = $this->manufacturingOrders->start($order);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $order, 'message' => 'Đã bắt đầu sản xuất.']);
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
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $result['order'],
            'unit_cost' => $result['unit_cost'],
            'warnings' => $result['warnings'],
            'message' => 'Đã hoàn thành sản xuất.',
        ]);
    }
}
