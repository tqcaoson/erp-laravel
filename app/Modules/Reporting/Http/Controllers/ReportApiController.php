<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Modules\Reporting\Services\ReportService;
use Illuminate\Http\Request;

/**
 * API báo cáo.
 * Ví dụ mục 9.1 thiết kế: GET /api/v1/reports/revenue-by-customer?from=2026-01-01&to=2026-03-31
 */
class ReportApiController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function revenueByCustomer(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $rows = $this->reports->revenueByCustomer(CurrentCompany::id() ?? 0, $from, $to);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total_revenue' => array_sum(array_column($rows, 'revenue')),
            'total_orders' => array_sum(array_column($rows, 'orders')),
            'data' => $rows,
        ]);
    }

    public function aging()
    {
        $buckets = $this->reports->aging(CurrentCompany::id() ?? 0);

        return response()->json([
            'total_amount' => array_sum(array_column($buckets, 'amount')),
            'total_count' => array_sum(array_column($buckets, 'count')),
            'data' => $buckets,
        ]);
    }

    public function stockMovement(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'data' => $this->reports->stockMovement(
                CurrentCompany::id() ?? 0, $from, $to, $validated['warehouse_id'] ?? null,
            ),
        ]);
    }
}
