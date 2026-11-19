<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Core\Services\DocumentNumberingService;
use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\ManufacturingOrder;
use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Trang web công đoạn sản xuất (Inertia).
 *
 * Công đoạn thường tự sinh từ routing khi bắt đầu lệnh, nhưng nhân viên
 * sản xuất được thêm/sửa/xóa tay để điều chỉnh kế hoạch trong lúc lệnh
 * đang chạy. Quy tắc:
 * - Chỉ thêm/sửa/xóa khi lệnh đang ở trạng thái in_progress (lệnh nháp
 *   chưa có công đoạn, lệnh đã xong/hủy thì khóa lại để giữ lịch sử).
 * - Thứ tự (sequence) duy nhất trong mỗi lệnh.
 * - Không xóa công đoạn đã hoàn thành hoặc đã có nhật ký sản xuất.
 */
class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkOrder::query()->with(['manufacturingOrder:id,code', 'workCenter:id,name']);

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        if ($request->filled('filter.manufacturing_order_id')) {
            $query->where('manufacturing_order_id', $request->input('filter.manufacturing_order_id'));
        }

        return Inertia::render('Manufacturing/WorkOrders/Index', [
            'workOrders' => $query->latest()->paginate(15)->withQueryString(),
            'statuses' => WorkOrder::STATUS_LABELS,
            'manufacturingOrders' => ManufacturingOrder::query()->orderByDesc('created_at')->get(['id', 'code']),
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create(Request $request)
    {
        $orders = ManufacturingOrder::query()
            ->where('status', 'in_progress')
            ->orderByDesc('created_at')
            ->get(['id', 'code']);

        // Thứ tự tiếp theo cho từng lệnh (số lớn nhất hiện có + 1) để form
        // tự điền khi chọn lệnh; người dùng vẫn sửa được.
        $nextSequences = [];
        foreach ($orders as $order) {
            $nextSequences[$order->id] = (int) WorkOrder::query()
                ->where('manufacturing_order_id', $order->id)
                ->max('sequence') + 1;
        }

        return Inertia::render('Manufacturing/WorkOrders/Create', [
            'manufacturingOrders' => $orders,
            'workCenters' => WorkCenter::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'nextSequences' => $nextSequences,
            'selectedOrderId' => $request->integer('manufacturing_order_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'manufacturing_order_id' => ['required', 'exists:manufacturing_orders,id'],
            'operation_name' => ['required', 'string', 'max:255'],
            'work_center_id' => ['required', 'exists:work_centers,id'],
            'sequence' => [
                'required', 'integer', 'min:1',
                Rule::unique('work_orders', 'sequence')->where(
                    'manufacturing_order_id',
                    $request->input('manufacturing_order_id')
                ),
            ],
            'planned_minutes' => ['required', 'numeric', 'min:0'],
            'actual_minutes' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = ManufacturingOrder::findOrFail($validated['manufacturing_order_id']);

        if ($order->status !== 'in_progress') {
            return back()->with('error', 'Chỉ thêm được công đoạn cho lệnh sản xuất đang thực hiện.');
        }

        $validated['code'] = app(DocumentNumberingService::class)->next('work_order');
        $validated['status'] = 'pending';

        WorkOrder::create($validated);

        return redirect()->route('manufacturing.work-orders.index', [
            'filter' => ['manufacturing_order_id' => $order->id],
        ])->with('success', 'Đã thêm công đoạn cho lệnh '.$order->code.'.');
    }

    public function edit(WorkOrder $workOrder)
    {
        $workOrder->load('manufacturingOrder:id,code,status');

        if ($workOrder->manufacturingOrder->status !== 'in_progress') {
            return back()->with('error', 'Lệnh sản xuất đã xong hoặc đã hủy nên không sửa được công đoạn.');
        }

        return Inertia::render('Manufacturing/WorkOrders/Edit', [
            'workOrder' => $workOrder,
            'workCenters' => WorkCenter::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $workOrder->load('manufacturingOrder:id,code,status');

        if ($workOrder->manufacturingOrder->status !== 'in_progress') {
            return back()->with('error', 'Lệnh sản xuất đã xong hoặc đã hủy nên không sửa được công đoạn.');
        }

        $validated = $request->validate([
            'operation_name' => ['required', 'string', 'max:255'],
            'work_center_id' => ['required', 'exists:work_centers,id'],
            'sequence' => [
                'required', 'integer', 'min:1',
                Rule::unique('work_orders', 'sequence')
                    ->where('manufacturing_order_id', $workOrder->manufacturing_order_id)
                    ->ignore($workOrder->id),
            ],
            'planned_minutes' => ['required', 'numeric', 'min:0'],
            'actual_minutes' => ['nullable', 'numeric', 'min:0'],
        ]);

        $workOrder->update($validated);

        return redirect()->route('manufacturing.work-orders.index', [
            'filter' => ['manufacturing_order_id' => $workOrder->manufacturing_order_id],
        ])->with('success', 'Đã cập nhật công đoạn.');
    }

    public function destroy(WorkOrder $workOrder)
    {
        $workOrder->load('manufacturingOrder:id,code,status');

        if ($workOrder->manufacturingOrder->status !== 'in_progress') {
            return back()->with('error', 'Lệnh sản xuất đã xong hoặc đã hủy nên không xóa được công đoạn.');
        }

        if ($workOrder->status === 'completed') {
            return back()->with('error', 'Công đoạn đã hoàn thành nên không xóa được (giữ lại làm lịch sử sản xuất).');
        }

        if ($workOrder->productionLogs()->exists()) {
            return back()->with('error', 'Công đoạn đã có nhật ký sản xuất nên không xóa được.');
        }

        $workOrder->delete();

        return back()->with('success', 'Đã xóa công đoạn.');
    }

    /** Cập nhật trạng thái công đoạn (bắt đầu / hoàn thành). */
    public function updateStatus(Request $request, WorkOrder $workOrder)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', WorkOrder::STATUSES)],
            'actual_minutes' => ['nullable', 'numeric', 'min:0'],
        ]);

        $workOrder->update($validated);

        return back()->with('success', 'Đã cập nhật trạng thái công đoạn.');
    }
}
