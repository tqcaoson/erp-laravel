<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockMoveRequest;
use App\Modules\Inventory\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Duyệt phiếu kho tay (Inertia): phiếu nhập/xuất/chuyển kho tạo tay ở
 * trạng thái chờ duyệt, chỉ khi duyệt mới ghi tồn qua StockService.
 */
class StockMoveRequestController extends Controller
{
    public function __construct(
        protected StockService $stockService,
    ) {}

    /** Danh sách phiếu chờ duyệt + lịch sử đã duyệt/từ chối. */
    public function index(Request $request)
    {
        $with = ['product:id,sku,name', 'warehouse:id,code,name', 'toWarehouse:id,code,name', 'requester:id,name', 'approver:id,name'];

        $pending = StockMoveRequest::query()->with($with)
            ->where('status', 'pending')
            ->latest()
            ->paginate(15, ['*'], 'pending_page')
            ->withQueryString();

        $history = StockMoveRequest::query()->with($with)
            ->whereIn('status', ['approved', 'rejected'])
            ->latest()
            ->paginate(15, ['*'], 'history_page')
            ->withQueryString();

        return Inertia::render('Inventory/Stock/Requests/Index', [
            'pendingRequests' => $pending,
            'historyRequests' => $history,
            'statusLabels' => StockMoveRequest::STATUS_LABELS,
            'typeLabels' => StockMoveRequest::TYPE_LABELS,
            'filters' => $request->only(['pending_page', 'history_page']),
        ]);
    }

    /** Duyệt phiếu: ghi tồn qua StockService rồi đổi trạng thái. */
    public function approve(StockMoveRequest $stockMoveRequest)
    {
        if ($stockMoveRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Chỉ phiếu đang chờ duyệt mới được duyệt.');
        }

        try {
            DB::transaction(function () use ($stockMoveRequest) {
                // Nguồn là phiếu kho tay đã duyệt (source_id = id phiếu).
                $sourceType = 'manual';
                $sourceId = $stockMoveRequest->id;

                match ($stockMoveRequest->type) {
                    'receive' => $this->stockService->receive(
                        $stockMoveRequest->product_id, $stockMoveRequest->warehouse_id, $stockMoveRequest->location_id,
                        (float) $stockMoveRequest->qty, (float) ($stockMoveRequest->unit_cost ?? 0), $sourceType, $sourceId, $stockMoveRequest->lot_id,
                    ),
                    'issue' => $this->stockService->issue(
                        $stockMoveRequest->product_id, $stockMoveRequest->warehouse_id, $stockMoveRequest->location_id,
                        (float) $stockMoveRequest->qty, $sourceType, $sourceId, $stockMoveRequest->lot_id,
                    ),
                    'transfer' => $this->stockService->transfer(
                        $stockMoveRequest->product_id, $stockMoveRequest->warehouse_id, $stockMoveRequest->to_warehouse_id,
                        $stockMoveRequest->location_id, $stockMoveRequest->to_location_id,
                        (float) $stockMoveRequest->qty, $sourceType, $sourceId,
                    ),
                };

                $stockMoveRequest->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                ]);
            });
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã duyệt phiếu kho, tồn kho đã được cập nhật.');
    }

    /** Từ chối phiếu: tồn kho không đổi. */
    public function reject(StockMoveRequest $stockMoveRequest)
    {
        if ($stockMoveRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Chỉ phiếu đang chờ duyệt mới được từ chối.');
        }

        $stockMoveRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Đã từ chối phiếu kho.');
    }
}
