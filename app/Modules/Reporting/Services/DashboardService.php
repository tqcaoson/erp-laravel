<?php

namespace App\Modules\Reporting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Số liệu dashboard.
 *
 * - KPI đọc từ bảng summary + cache (TTL 5 phút theo mục 4.7 thiết kế).
 *   Cache dùng driver đã cấu hình (Redis ở production, database/file ở
 *   môi trường dev) nên không phụ thuộc Redis phải chạy.
 * - Module Reporting KHÔNG import model của module khác: mọi số liệu
 *   xuyên module đều đọc bằng query builder trên tên bảng (hợp đồng mục 3).
 * - Mỗi khối query bọc try/catch riêng + kiểm tra Schema::hasTable:
 *   dashboard phải luôn mở được, thiếu bảng/dữ liệu thì KPI hiển thị 0
 *   thay vì lỗi 500.
 */
class DashboardService
{
    public const CACHE_TTL_SECONDS = 300; // 5 phút

    public static function kpiCacheKey(int $companyId): string
    {
        return 'dashboard:kpi:'.$companyId;
    }

    public static function forgetKpiCache(int $companyId): void
    {
        Cache::forget(self::kpiCacheKey($companyId));
    }

    /**
     * Các thẻ KPI chính (có cache 5 phút).
     * KPI theo vai trò (mục 7.3 thiết kế): tất cả vai trò đều thấy bộ
     * KPI chung này; việc ẩn/hiện theo vai trò để trang Vue quyết định
     * bằng usePermission khi cần.
     */
    public function kpi(int $companyId): array
    {
        return Cache::remember(self::kpiCacheKey($companyId), self::CACHE_TTL_SECONDS, function () use ($companyId) {
            return [
                'revenue_this_month' => $this->revenueThisMonth($companyId),
                'orders_this_month' => $this->ordersThisMonth($companyId),
                'stock_value' => $this->stockValue($companyId),
                'receivables' => $this->receivables($companyId),
                'active_employees' => $this->activeEmployees($companyId),
                'pending_leave_requests' => $this->pendingLeaveRequests($companyId),
            ];
        });
    }

    /** Doanh thu tháng này: ưu tiên summary, fallback query invoices. */
    protected function revenueThisMonth(int $companyId): float
    {
        try {
            if (Schema::hasTable('daily_sales_summary')) {
                $from = now()->startOfMonth()->toDateString();
                $sum = (float) DB::table('daily_sales_summary')
                    ->where('company_id', $companyId)
                    ->where('date', '>=', $from)
                    ->sum('total_revenue');

                if ($sum > 0) {
                    return $sum;
                }
            }

            return $this->revenueFromInvoices($companyId, now()->startOfMonth(), now()->endOfMonth());
        } catch (\Throwable) {
            return 0.0;
        }
    }

    protected function ordersThisMonth(int $companyId): int
    {
        try {
            if (Schema::hasTable('daily_sales_summary')) {
                $count = (int) DB::table('daily_sales_summary')
                    ->where('company_id', $companyId)
                    ->where('date', '>=', now()->startOfMonth()->toDateString())
                    ->sum('total_orders');

                if ($count > 0) {
                    return $count;
                }
            }

            if (! Schema::hasTable('invoices')) {
                return 0;
            }

            return (int) DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('type', 'sale')
                ->whereIn('status', ['posted', 'paid'])
                ->whereBetween('invoice_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Giá trị tồn kho = SUM(qty × average_cost) từ stock_quants + products. */
    protected function stockValue(int $companyId): float
    {
        try {
            if (! Schema::hasTable('stock_quants') || ! Schema::hasTable('products')) {
                return 0.0;
            }

            return (float) DB::table('stock_quants')
                ->join('products', 'products.id', '=', 'stock_quants.product_id')
                ->where('stock_quants.company_id', $companyId)
                ->sum(DB::raw('stock_quants.qty * products.average_cost'));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** Công nợ phải thu = tổng hóa đơn bán đã ghi sổ (chưa thanh toán hết). */
    protected function receivables(int $companyId): float
    {
        try {
            if (! Schema::hasTable('invoices')) {
                return 0.0;
            }

            return (float) DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('type', 'sale')
                ->where('status', 'posted')
                ->sum('total_amount');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    protected function activeEmployees(int $companyId): int
    {
        try {
            if (! Schema::hasTable('employees')) {
                return 0;
            }

            return (int) DB::table('employees')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function pendingLeaveRequests(int $companyId): int
    {
        try {
            if (! Schema::hasTable('leave_requests')) {
                return 0;
            }

            return (int) DB::table('leave_requests')
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Cảnh báo: tồn dưới mức tối thiểu (top 5), hóa đơn quá hạn, hợp đồng sắp hết hạn. */
    public function alerts(int $companyId): array
    {
        return [
            'low_stock' => $this->lowStockProducts($companyId),
            'overdue_invoices' => $this->overdueInvoices($companyId),
            'expiring_contracts' => $this->expiringContracts($companyId),
        ];
    }

    protected function lowStockProducts(int $companyId): array
    {
        try {
            if (! Schema::hasTable('products') || ! Schema::hasTable('stock_quants')) {
                return [];
            }

            return DB::table('products')
                ->leftJoin('stock_quants', 'stock_quants.product_id', '=', 'products.id')
                ->where('products.company_id', $companyId)
                ->where('products.reorder_min', '>', 0)
                ->groupBy('products.id', 'products.sku', 'products.name', 'products.reorder_min')
                ->havingRaw('COALESCE(SUM(stock_quants.qty), 0) < products.reorder_min')
                ->orderByRaw('COALESCE(SUM(stock_quants.qty), 0) ASC')
                ->limit(5)
                ->select([
                    'products.id', 'products.sku', 'products.name', 'products.reorder_min',
                    DB::raw('COALESCE(SUM(stock_quants.qty), 0) as qty'),
                ])
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'sku' => $row->sku,
                    'name' => $row->name,
                    'qty' => (float) $row->qty,
                    'reorder_min' => (float) $row->reorder_min,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function overdueInvoices(int $companyId): array
    {
        try {
            if (! Schema::hasTable('invoices')) {
                return [];
            }

            return DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('type', 'sale')
                ->where('status', 'posted')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->orderBy('due_date')
                ->limit(5)
                ->select(['id', 'code', 'customer_id', 'due_date', 'total_amount'])
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'customer_id' => $row->customer_id,
                    'due_date' => $row->due_date,
                    'total_amount' => (float) $row->total_amount,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function expiringContracts(int $companyId): array
    {
        try {
            if (! Schema::hasTable('contracts') || ! Schema::hasTable('employees')) {
                return [];
            }

            return DB::table('contracts')
                ->join('employees', 'employees.id', '=', 'contracts.employee_id')
                ->where('contracts.company_id', $companyId)
                ->where('contracts.status', 'active')
                ->whereNotNull('contracts.end_date')
                ->whereBetween('contracts.end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->orderBy('contracts.end_date')
                ->limit(5)
                ->select(['contracts.id', 'contracts.code', 'contracts.end_date', 'employees.full_name'])
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'end_date' => $row->end_date,
                    'employee_name' => $row->full_name,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Doanh thu 6 tháng gần nhất (từ summary, fallback về query invoices
     * nếu summary trống). Trả về đủ 6 tháng, tháng không có số liệu = 0.
     */
    public function monthlyRevenue(int $companyId): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonthsNoOverflow($i);
            $months[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'label' => 'Tháng '.$month->format('m/Y'),
                'revenue' => 0.0,
                'orders' => 0,
            ];
        }

        try {
            if (Schema::hasTable('daily_sales_summary')) {
                $rows = DB::table('daily_sales_summary')
                    ->where('company_id', $companyId)
                    ->where('date', '>=', now()->subMonthsNoOverflow(5)->startOfMonth()->toDateString())
                    ->selectRaw("substr(date, 1, 7) as month, SUM(total_revenue) as revenue, SUM(total_orders) as orders")
                    ->groupBy('month')
                    ->get();

                foreach ($rows as $row) {
                    if (isset($months[$row->month])) {
                        $months[$row->month]['revenue'] = (float) $row->revenue;
                        $months[$row->month]['orders'] = (int) $row->orders;
                    }
                }

                // Summary có số liệu thì dùng luôn, không fallback.
                if ($rows->sum('revenue') > 0 || $rows->sum('orders') > 0) {
                    return array_values($months);
                }
            }

            // Fallback: tính trực tiếp từ invoices.
            if (Schema::hasTable('invoices')) {
                $rows = DB::table('invoices')
                    ->where('company_id', $companyId)
                    ->where('type', 'sale')
                    ->whereIn('status', ['posted', 'paid'])
                    ->where('invoice_date', '>=', now()->subMonthsNoOverflow(5)->startOfMonth()->toDateString())
                    ->selectRaw("substr(invoice_date, 1, 7) as month, SUM(total_amount) as revenue, COUNT(*) as orders")
                    ->groupBy('month')
                    ->get();

                foreach ($rows as $row) {
                    if (isset($months[$row->month])) {
                        $months[$row->month]['revenue'] = (float) $row->revenue;
                        $months[$row->month]['orders'] = (int) $row->orders;
                    }
                }
            }
        } catch (\Throwable) {
            // Giữ nguyên các tháng = 0, dashboard vẫn mở được.
        }

        return array_values($months);
    }

    protected function revenueFromInvoices(int $companyId, $from, $to): float
    {
        if (! Schema::hasTable('invoices')) {
            return 0.0;
        }

        return (float) DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('type', 'sale')
            ->whereIn('status', ['posted', 'paid'])
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->sum('total_amount');
    }
}
