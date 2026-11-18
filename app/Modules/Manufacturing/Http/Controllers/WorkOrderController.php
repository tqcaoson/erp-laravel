<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web danh sách công đoạn sản xuất (Inertia). */
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
            'filters' => $request->only(['filter']),
        ]);
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
