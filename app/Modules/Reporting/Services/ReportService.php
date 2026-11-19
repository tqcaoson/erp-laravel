<?php

namespace App\Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Các báo cáo chi tiết (đọc bằng query builder, KHÔNG import model
 * của module khác theo hợp đồng mục 3).
 */
class ReportService
{
    /** Doanh thu theo khách hàng trong khoảng thời gian (hóa đơn bán đã ghi sổ). */
    public function revenueByCustomer(int $companyId, string $from, string $to): array
    {
        if (! Schema::hasTable('invoices')) {
            return [];
        }

        $hasCustomers = Schema::hasTable('customers');

        $query = DB::table('invoices')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.type', 'sale')
            ->whereIn('invoices.status', ['posted', 'paid'])
            ->whereBetween('invoices.invoice_date', [$from, $to]);

        if ($hasCustomers) {
            $query->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
                ->groupBy('invoices.customer_id', 'customers.code', 'customers.name');
            $select = [
                'invoices.customer_id',
                'customers.code as customer_code',
                'customers.name as customer_name',
            ];
        } else {
            $query->groupBy('invoices.customer_id');
            $select = ['invoices.customer_id'];
        }

        $rows = $query
            ->select([...$select,
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(invoices.total_amount) as revenue'),
            ])
            ->orderByDesc('revenue')
            ->get();

        return $rows->map(fn ($row) => [
            'customer_id' => $row->customer_id,
            'customer_code' => $row->customer_code ?? null,
            'customer_name' => $row->customer_name ?? ($row->customer_id ? 'Khách hàng #'.$row->customer_id : 'Khách lẻ'),
            'orders' => (int) $row->orders,
            'revenue' => (float) $row->revenue,
        ])->all();
    }

    /**
     * Công nợ tuổi nợ theo nhóm 0–30 / 31–60 / 61–90 / trên 90 ngày
     * (tính theo ngày quá hạn so với due_date; hóa đơn chưa tới hạn vào nhóm 0–30).
     */
    public function aging(int $companyId): array
    {
        $buckets = [
            ['key' => '0-30', 'label' => '0–30 ngày', 'min' => 0, 'max' => 30],
            ['key' => '31-60', 'label' => '31–60 ngày', 'min' => 31, 'max' => 60],
            ['key' => '61-90', 'label' => '61–90 ngày', 'min' => 61, 'max' => 90],
            ['key' => '90+', 'label' => 'Trên 90 ngày', 'min' => 91, 'max' => null],
        ];

        $result = [];
        foreach ($buckets as $bucket) {
            $result[] = [...$bucket, 'count' => 0, 'amount' => 0.0, 'invoices' => []];
        }

        if (! Schema::hasTable('invoices')) {
            return $result;
        }

        $hasCustomers = Schema::hasTable('customers');
        $query = DB::table('invoices')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.type', 'sale')
            ->where('invoices.status', 'posted');

        if ($hasCustomers) {
            $query->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id');
        }

        $rows = $query->select([
            'invoices.id', 'invoices.code', 'invoices.customer_id',
            'invoices.invoice_date', 'invoices.due_date', 'invoices.total_amount',
            ...($hasCustomers ? ['customers.name as customer_name'] : []),
        ])->orderBy('invoices.due_date')->get();

        $today = now()->startOfDay();

        foreach ($rows as $row) {
            $dueDate = $row->due_date ? \Carbon\Carbon::parse($row->due_date)->startOfDay() : null;
            // Số ngày quá hạn; chưa tới hạn tính là 0 ngày (nhóm 0–30).
            $overdueDays = ($dueDate && $dueDate->lessThan($today))
                ? (int) $dueDate->diffInDays($today)
                : 0;

            foreach ($result as &$bucket) {
                $inBucket = $overdueDays >= $bucket['min']
                    && ($bucket['max'] === null || $overdueDays <= $bucket['max']);
                if ($inBucket) {
                    $bucket['count']++;
                    $bucket['amount'] += (float) $row->total_amount;
                    $bucket['invoices'][] = [
                        'id' => $row->id,
                        'code' => $row->code,
                        'customer_name' => $row->customer_name ?? ($row->customer_id ? 'Khách hàng #'.$row->customer_id : 'Khách lẻ'),
                        'invoice_date' => $row->invoice_date,
                        'due_date' => $row->due_date,
                        'overdue_days' => $overdueDays,
                        'total_amount' => (float) $row->total_amount,
                    ];
                    break;
                }
            }
            unset($bucket);
        }

        return $result;
    }

    /**
     * Công nợ phải trả theo nhà cung cấp — đọc trực tiếp từ sổ cái.
     *
     * Số dư = SUM(Có − Nợ) của các dòng bút toán đã ghi sổ trên tài khoản
     * 331/3311, nhóm theo nhà cung cấp (partner_type='supplier').
     * journal_entry_lines KHÔNG có company_id nên phải join qua
     * journal_entries để lọc công ty. Tuổi nợ tính theo ngày phát sinh
     * bút toán (entry_date), chia nhóm 0–30 / 31–60 / 61–90 / trên 90 ngày.
     *
     * @return array<int, array{supplier_id: int|null, supplier_code: string|null, supplier_name: string, balance: float, buckets: array<string, float>}>
     */
    public function payablesBySupplier(int $companyId): array
    {
        return $this->ledgerBalancesBySupplier($companyId, ['331', '3311']);
    }

    /**
     * Hàng đã nhận nhưng chưa có hóa đơn (TK 335) theo nhà cung cấp.
     *
     * Phiếu nhận đã xác nhận ghi Nợ 156 / Có 335; khi hóa đơn mua từ
     * phiếu nhận được ghi sổ thì 335 được kết chuyển sang 331. Mục này
     * cho thấy phần hàng đã về kho mà kế toán chưa lập hóa đơn mua.
     *
     * @return array<int, array{supplier_id: int|null, supplier_code: string|null, supplier_name: string, balance: float, buckets: array<string, float>}>
     */
    public function goodsReceivedNotInvoiced(int $companyId): array
    {
        return $this->ledgerBalancesBySupplier($companyId, ['335']);
    }

    /**
     * Số dư sổ cái theo nhà cung cấp cho một nhóm tài khoản.
     */
    private function ledgerBalancesBySupplier(int $companyId, array $codes): array
    {
        if (! Schema::hasTable('journal_entry_lines')
            || ! Schema::hasTable('journal_entries')
            || ! Schema::hasTable('chart_of_accounts')) {
            return [];
        }

        $bucketKeys = ['0-30', '31-60', '61-90', '90+'];

        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereIn('chart_of_accounts.code', $codes)
            ->where('journal_entry_lines.partner_type', 'supplier');

        $hasSuppliers = Schema::hasTable('suppliers');
        if ($hasSuppliers) {
            $query->leftJoin('suppliers', 'suppliers.id', '=', 'journal_entry_lines.partner_id');
        }

        $rows = $query->select([
            'journal_entry_lines.partner_id',
            'journal_entry_lines.debit',
            'journal_entry_lines.credit',
            'journal_entries.entry_date',
            ...($hasSuppliers ? ['suppliers.code as supplier_code', 'suppliers.name as supplier_name'] : []),
        ])->orderBy('journal_entries.entry_date')->get();

        $today = now()->startOfDay();
        $bySupplier = [];

        foreach ($rows as $row) {
            $key = (int) $row->partner_id;
            $amount = (float) $row->credit - (float) $row->debit;

            if (! isset($bySupplier[$key])) {
                $bySupplier[$key] = [
                    'supplier_id' => $key,
                    'supplier_code' => $row->supplier_code ?? null,
                    'supplier_name' => $row->supplier_name ?? ('Nhà cung cấp #'.$key),
                    'balance' => 0.0,
                    'buckets' => array_fill_keys($bucketKeys, 0.0),
                ];
            }

            $days = (int) \Carbon\Carbon::parse($row->entry_date)->startOfDay()->diffInDays($today);
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));

            $bySupplier[$key]['balance'] += $amount;
            $bySupplier[$key]['buckets'][$bucket] += $amount;
        }

        // Chỉ hiển thị nhà cung cấp còn số dư (bỏ qua các NCC đã thanh toán hết).
        $result = array_values(array_filter(
            $bySupplier,
            fn ($row) => abs($row['balance']) >= 0.01,
        ));

        usort($result, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        return $result;
    }

    /**
     * Báo cáo xuất nhập tồn theo khoảng thời gian.
     * Ưu tiên daily_stock_summary; nếu trống thì tổng hợp từ stock_moves.
     */
    public function stockMovement(int $companyId, string $from, string $to, ?int $warehouseId = null): array
    {
        $rows = [];

        if (Schema::hasTable('daily_stock_summary') && Schema::hasTable('products')) {
            $query = DB::table('daily_stock_summary')
                ->join('products', 'products.id', '=', 'daily_stock_summary.product_id')
                ->where('daily_stock_summary.company_id', $companyId)
                ->whereBetween('daily_stock_summary.date', [$from, $to])
                ->when($warehouseId, fn ($q) => $q->where('daily_stock_summary.warehouse_id', $warehouseId))
                ->groupBy('daily_stock_summary.product_id', 'products.sku', 'products.name')
                ->select([
                    'daily_stock_summary.product_id',
                    'products.sku', 'products.name',
                    DB::raw('SUM(opening_qty) as opening_qty'),
                    DB::raw('SUM(in_qty) as in_qty'),
                    DB::raw('SUM(out_qty) as out_qty'),
                    DB::raw('SUM(closing_qty) as closing_qty'),
                ])
                ->orderBy('products.name')
                ->get();

            // opening lấy ngày đầu kỳ, closing lấy ngày cuối kỳ mới đúng;
            // ở đây dùng tổng đơn giản cho bản demo (ghi chú trong trang báo cáo).
            $rows = $query->map(fn ($row) => [
                'product_id' => $row->product_id,
                'sku' => $row->sku,
                'name' => $row->name,
                'opening_qty' => (float) $row->opening_qty,
                'in_qty' => (float) $row->in_qty,
                'out_qty' => (float) $row->out_qty,
                'closing_qty' => (float) $row->closing_qty,
            ])->all();
        }

        if ($rows === [] && Schema::hasTable('stock_moves') && Schema::hasTable('products')) {
            $query = DB::table('stock_moves')
                ->join('products', 'products.id', '=', 'stock_moves.product_id')
                ->where('stock_moves.company_id', $companyId)
                ->whereBetween('stock_moves.moved_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->when($warehouseId, fn ($q) => $q->where('stock_moves.warehouse_id', $warehouseId))
                ->groupBy('stock_moves.product_id', 'products.sku', 'products.name')
                ->select([
                    'stock_moves.product_id',
                    'products.sku', 'products.name',
                    DB::raw("SUM(CASE WHEN stock_moves.qty > 0 THEN stock_moves.qty ELSE 0 END) as in_qty"),
                    DB::raw("SUM(CASE WHEN stock_moves.qty < 0 THEN -stock_moves.qty ELSE 0 END) as out_qty"),
                ])
                ->orderBy('products.name')
                ->get();

            $rows = $query->map(fn ($row) => [
                'product_id' => $row->product_id,
                'sku' => $row->sku,
                'name' => $row->name,
                'opening_qty' => 0.0, // Không tính được tồn đầu kỳ từ stock_moves đơn thuần.
                'in_qty' => (float) $row->in_qty,
                'out_qty' => (float) $row->out_qty,
                'closing_qty' => (float) $row->in_qty - (float) $row->out_qty,
            ])->all();
        }

        return $rows;
    }
}
