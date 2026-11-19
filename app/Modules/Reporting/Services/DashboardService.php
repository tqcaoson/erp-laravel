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
                'payables' => $this->payables($companyId),
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

    /**
     * Công nợ phải thu = số dư Nợ tài khoản 131 trên sổ cái.
     * Đọc từ sổ cái (không cộng tổng hóa đơn) để đã trừ tiền khách đã
     * trả — khớp với báo cáo công nợ tuổi nợ.
     */
    protected function receivables(int $companyId): float
    {
        try {
            if (! Schema::hasTable('journal_entry_lines') || ! Schema::hasTable('journal_entries') || ! Schema::hasTable('chart_of_accounts')) {
                return 0.0;
            }

            return (float) DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.status', 'posted')
                ->whereIn('chart_of_accounts.code', ['131', '1311'])
                ->where('journal_entry_lines.partner_type', 'customer')
                ->sum(DB::raw('journal_entry_lines.debit - journal_entry_lines.credit'));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Công nợ phải trả = số dư Có tài khoản 331 trên sổ cái.
     * Đọc từ sổ cái (không cộng tổng hóa đơn) để đã trừ tiền đã trả NCC
     * — khớp với báo cáo công nợ phải trả theo nhà cung cấp.
     */
    protected function payables(int $companyId): float
    {
        try {
            if (! Schema::hasTable('journal_entry_lines') || ! Schema::hasTable('journal_entries') || ! Schema::hasTable('chart_of_accounts')) {
                return 0.0;
            }

            return (float) DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.status', 'posted')
                ->whereIn('chart_of_accounts.code', ['331', '3311'])
                ->where('journal_entry_lines.partner_type', 'supplier')
                ->sum(DB::raw('journal_entry_lines.credit - journal_entry_lines.debit'));
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

    /**
     * Cảnh báo: tồn dưới mức tối thiểu (top 5), hóa đơn quá hạn, hợp đồng sắp hết hạn,
     * phiếu nhận hàng chờ xác nhận (top 5).
     * Mỗi loại kèm tổng số mục (để giao diện hiển thị "Đang hiển thị 5 / N mục").
     */
    public function alerts(int $companyId): array
    {
        return [
            'low_stock' => $this->lowStockProducts($companyId),
            'low_stock_total' => $this->lowStockTotal($companyId),
            'overdue_invoices' => $this->overdueInvoices($companyId),
            'overdue_invoices_total' => $this->overdueInvoicesTotal($companyId),
            'expiring_contracts' => $this->expiringContracts($companyId),
            'expiring_contracts_total' => $this->expiringContractsTotal($companyId),
            'pending_goods_receipts' => $this->pendingGoodsReceipts($companyId),
            'pending_goods_receipts_total' => $this->pendingGoodsReceiptsTotal($companyId),
        ];
    }

    /** Query gốc: sản phẩm có tồn tổng dưới mức tối thiểu. */
    protected function lowStockQuery(int $companyId)
    {
        return DB::table('products')
            ->leftJoin('stock_quants', 'stock_quants.product_id', '=', 'products.id')
            ->where('products.company_id', $companyId)
            ->where('products.reorder_min', '>', 0)
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.reorder_min')
            ->havingRaw('COALESCE(SUM(stock_quants.qty), 0) < products.reorder_min');
    }

    protected function lowStockProducts(int $companyId): array
    {
        try {
            if (! Schema::hasTable('products') || ! Schema::hasTable('stock_quants')) {
                return [];
            }

            return $this->lowStockQuery($companyId)
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
                    // Số lượng còn thiếu để về mức tối thiểu.
                    'shortage' => max(0, (float) $row->reorder_min - (float) $row->qty),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function lowStockTotal(int $companyId): int
    {
        try {
            if (! Schema::hasTable('products') || ! Schema::hasTable('stock_quants')) {
                return 0;
            }

            return (int) $this->lowStockQuery($companyId)->select('products.id')->get()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Query gốc: hóa đơn bán đã ghi sổ, quá hạn thanh toán. */
    protected function overdueInvoicesQuery(int $companyId)
    {
        return DB::table('invoices')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.type', 'sale')
            ->where('invoices.status', 'posted')
            ->whereNotNull('invoices.due_date')
            ->where('invoices.due_date', '<', now()->toDateString());
    }

    protected function overdueInvoices(int $companyId): array
    {
        try {
            if (! Schema::hasTable('invoices')) {
                return [];
            }

            $query = $this->overdueInvoicesQuery($companyId);

            // Tên khách hàng chỉ join khi bảng customers tồn tại.
            $withCustomer = Schema::hasTable('customers');
            if ($withCustomer) {
                $query->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id');
            }

            $select = ['invoices.id', 'invoices.code', 'invoices.customer_id', 'invoices.due_date', 'invoices.total_amount'];
            if ($withCustomer) {
                $select[] = 'customers.name as customer_name';
            }

            return $query
                ->orderBy('invoices.due_date')
                ->limit(5)
                ->select($select)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'customer_id' => $row->customer_id,
                    'customer_name' => $row->customer_name ?? null,
                    'due_date' => $row->due_date,
                    // Số ngày từ hạn thanh toán đến hôm nay.
                    'days_overdue' => (int) \Carbon\Carbon::parse($row->due_date)->diffInDays(now()->startOfDay()),
                    'total_amount' => (float) $row->total_amount,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function overdueInvoicesTotal(int $companyId): int
    {
        try {
            if (! Schema::hasTable('invoices')) {
                return 0;
            }

            return (int) $this->overdueInvoicesQuery($companyId)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Query gốc: hợp đồng đang hiệu lực, hết hạn trong 30 ngày tới. */
    protected function expiringContractsQuery(int $companyId)
    {
        return DB::table('contracts')
            ->join('employees', 'employees.id', '=', 'contracts.employee_id')
            ->where('contracts.company_id', $companyId)
            ->where('contracts.status', 'active')
            ->whereNotNull('contracts.end_date')
            ->whereBetween('contracts.end_date', [now()->toDateString(), now()->addDays(30)->toDateString()]);
    }

    protected function expiringContracts(int $companyId): array
    {
        try {
            if (! Schema::hasTable('contracts') || ! Schema::hasTable('employees')) {
                return [];
            }

            return $this->expiringContractsQuery($companyId)
                ->orderBy('contracts.end_date')
                ->limit(5)
                ->select(['contracts.id', 'contracts.code', 'contracts.employee_id', 'contracts.end_date', 'employees.full_name'])
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'employee_id' => $row->employee_id,
                    'end_date' => $row->end_date,
                    'employee_name' => $row->full_name,
                    // Số ngày còn lại đến ngày hết hạn.
                    'days_left' => (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($row->end_date)),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function expiringContractsTotal(int $companyId): int
    {
        try {
            if (! Schema::hasTable('contracts') || ! Schema::hasTable('employees')) {
                return 0;
            }

            return (int) $this->expiringContractsQuery($companyId)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Query gốc: phiếu nhận hàng đang ở trạng thái nháp (chờ xác nhận). */
    protected function pendingGoodsReceiptsQuery(int $companyId)
    {
        return DB::table('goods_receipts')
            ->where('goods_receipts.company_id', $companyId)
            ->where('goods_receipts.status', 'draft');
    }

    protected function pendingGoodsReceipts(int $companyId): array
    {
        try {
            if (! Schema::hasTable('goods_receipts')) {
                return [];
            }

            $query = $this->pendingGoodsReceiptsQuery($companyId);

            // Tên nhà cung cấp chỉ join khi bảng suppliers tồn tại.
            $withSupplier = Schema::hasTable('suppliers');
            if ($withSupplier) {
                $query->leftJoin('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id');
            }

            $select = ['goods_receipts.id', 'goods_receipts.code', 'goods_receipts.supplier_id', 'goods_receipts.receipt_date'];
            if ($withSupplier) {
                $select[] = 'suppliers.name as supplier_name';
            }

            return $query
                ->orderBy('goods_receipts.receipt_date')
                ->limit(5)
                ->select($select)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name ?? null,
                    'receipt_date' => $row->receipt_date,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function pendingGoodsReceiptsTotal(int $companyId): int
    {
        try {
            if (! Schema::hasTable('goods_receipts')) {
                return 0;
            }

            return (int) $this->pendingGoodsReceiptsQuery($companyId)->count();
        } catch (\Throwable) {
            return 0;
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
