<?php

namespace App\Modules\Reporting\Jobs;

use App\Core\Services\CurrentCompany;
use App\Modules\Reporting\Models\DailySalesSummary;
use App\Modules\Reporting\Models\DailyStockSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cập nhật bảng tổng hợp daily_sales_summary + daily_stock_summary
 * cho 1 ngày (idempotent: chạy lại cùng ngày chỉ ghi đè).
 */
class UpdateDailySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $companyId,
        public string $date, // 'YYYY-MM-DD'
    ) {}

    public function handle(): void
    {
        CurrentCompany::setId($this->companyId);

        $this->updateSalesSummary();
        $this->updateStockSummary();
    }

    protected function updateSalesSummary(): void
    {
        if (! Schema::hasTable('daily_sales_summary') || ! Schema::hasTable('invoices')) {
            return;
        }

        $row = DB::table('invoices')
            ->where('company_id', $this->companyId)
            ->where('type', 'sale')
            ->whereIn('status', ['posted', 'paid'])
            ->whereDate('invoice_date', $this->date)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue, COUNT(*) as orders')
            ->first();

        // Giá vốn: tổng qty × unit_cost của phiếu xuất trong ngày (nếu có bảng).
        $cogs = 0.0;
        if (Schema::hasTable('stock_moves')) {
            $cogs = (float) DB::table('stock_moves')
                ->where('company_id', $this->companyId)
                ->whereDate('moved_at', $this->date)
                ->where('qty', '<', 0)
                ->sum(DB::raw('-qty * unit_cost'));
        }

        DailySalesSummary::updateOrCreate(
            ['company_id' => $this->companyId, 'date' => $this->date],
            [
                'total_revenue' => (float) ($row->revenue ?? 0),
                'total_orders' => (int) ($row->orders ?? 0),
                'total_cogs' => $cogs,
            ],
        );
    }

    protected function updateStockSummary(): void
    {
        if (! Schema::hasTable('daily_stock_summary') || ! Schema::hasTable('stock_moves')) {
            return;
        }

        // Nhập/xuất trong ngày theo sản phẩm + kho.
        $moves = DB::table('stock_moves')
            ->where('company_id', $this->companyId)
            ->whereDate('moved_at', $this->date)
            ->whereNotNull('warehouse_id')
            ->groupBy('product_id', 'warehouse_id')
            ->select([
                'product_id', 'warehouse_id',
                DB::raw("SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END) as in_qty"),
                DB::raw("SUM(CASE WHEN qty < 0 THEN -qty ELSE 0 END) as out_qty"),
            ])
            ->get();

        foreach ($moves as $move) {
            // Tồn đầu ngày = tồn hiện tại (stock_quants) trừ biến động
            // từ ngày đó đến nay — bản đơn giản: lấy tồn cuối ngày hôm
            // trước từ summary nếu có, không thì dùng 0.
            $previous = DailyStockSummary::where('company_id', $this->companyId)
                ->where('product_id', $move->product_id)
                ->where('warehouse_id', $move->warehouse_id)
                ->where('date', '<', $this->date)
                ->orderByDesc('date')
                ->first();

            $opening = $previous ? (float) $previous->closing_qty : 0.0;
            $in = (float) $move->in_qty;
            $out = (float) $move->out_qty;

            DailyStockSummary::updateOrCreate(
                [
                    'company_id' => $this->companyId,
                    'date' => $this->date,
                    'product_id' => $move->product_id,
                    'warehouse_id' => $move->warehouse_id,
                ],
                [
                    'opening_qty' => $opening,
                    'in_qty' => $in,
                    'out_qty' => $out,
                    'closing_qty' => $opening + $in - $out,
                ],
            );
        }
    }
}
