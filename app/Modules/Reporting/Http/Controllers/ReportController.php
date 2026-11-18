<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Modules\Reporting\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Trang báo cáo (Inertia) + xuất CSV.
 * Xuất file dùng CSV (response()->streamDownload, không cài thêm package).
 * TODO: xuất PDF — chưa làm trong giai đoạn này, cần thêm package
 * (ví dụ dompdf) và layout in riêng ở giai đoạn sau.
 */
class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    /** Trang liệt kê các báo cáo có sẵn. */
    public function index()
    {
        return Inertia::render('Reporting/Index', [
            'reports' => [
                [
                    'key' => 'revenue-by-customer',
                    'name' => 'Doanh thu theo khách hàng',
                    'description' => 'Tổng doanh thu và số đơn theo từng khách hàng trong khoảng thời gian.',
                    'url' => route('reports.revenue-by-customer'),
                ],
                [
                    'key' => 'aging',
                    'name' => 'Công nợ tuổi nợ',
                    'description' => 'Hóa đơn phải thu chia nhóm quá hạn 0–30 / 31–60 / 61–90 / trên 90 ngày.',
                    'url' => route('reports.aging'),
                ],
                [
                    'key' => 'stock-movement',
                    'name' => 'Xuất nhập tồn',
                    'description' => 'Tồn đầu kỳ, nhập, xuất, tồn cuối kỳ theo sản phẩm (từ bảng tổng hợp theo ngày).',
                    'url' => route('reports.stock-movement'),
                ],
            ],
        ]);
    }

    /** Doanh thu theo khách hàng, lọc theo khoảng thời gian. */
    public function revenueByCustomer(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $rows = $this->reports->revenueByCustomer(CurrentCompany::id() ?? 0, $from, $to);

        if ($request->input('export') === 'csv') {
            return $this->downloadCsv(
                "doanh-thu-theo-khach-{$from}-{$to}.csv",
                ['Mã khách', 'Tên khách hàng', 'Số đơn', 'Doanh thu (VND)'],
                array_map(fn ($r) => [$r['customer_code'], $r['customer_name'], $r['orders'], $r['revenue']], $rows),
            );
        }

        return Inertia::render('Reporting/RevenueByCustomer', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'totalRevenue' => array_sum(array_column($rows, 'revenue')),
            'totalOrders' => array_sum(array_column($rows, 'orders')),
        ]);
    }

    /** Công nợ tuổi nợ. */
    public function aging(Request $request)
    {
        $buckets = $this->reports->aging(CurrentCompany::id() ?? 0);

        if ($request->input('export') === 'csv') {
            $lines = [];
            foreach ($buckets as $bucket) {
                foreach ($bucket['invoices'] as $invoice) {
                    $lines[] = [
                        $bucket['label'], $invoice['code'], $invoice['customer_name'],
                        $invoice['invoice_date'], $invoice['due_date'],
                        $invoice['overdue_days'], $invoice['total_amount'],
                    ];
                }
            }

            return $this->downloadCsv(
                'cong-no-tuoi-no-'.now()->format('Y-m-d').'.csv',
                ['Nhóm tuổi nợ', 'Số hóa đơn', 'Khách hàng', 'Ngày hóa đơn', 'Hạn thanh toán', 'Số ngày quá hạn', 'Số tiền (VND)'],
                $lines,
            );
        }

        return Inertia::render('Reporting/Aging', [
            'buckets' => $buckets,
            'totalAmount' => array_sum(array_column($buckets, 'amount')),
            'totalCount' => array_sum(array_column($buckets, 'count')),
        ]);
    }

    /** Xuất nhập tồn theo khoảng thời gian (lọc theo kho nếu có). */
    public function stockMovement(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $warehouseId = $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null;

        $rows = $this->reports->stockMovement(CurrentCompany::id() ?? 0, $from, $to, $warehouseId);

        if ($request->input('export') === 'csv') {
            return $this->downloadCsv(
                "xuat-nhap-ton-{$from}-{$to}.csv",
                ['SKU', 'Tên sản phẩm', 'Tồn đầu kỳ', 'Nhập', 'Xuất', 'Tồn cuối kỳ'],
                array_map(fn ($r) => [$r['sku'], $r['name'], $r['opening_qty'], $r['in_qty'], $r['out_qty'], $r['closing_qty']], $rows),
            );
        }

        return Inertia::render('Reporting/StockMovement', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'warehouseId' => $warehouseId,
        ]);
    }

    /** Xuất CSV bằng stream (không cài thêm package). */
    protected function downloadCsv(string $filename, array $headers, array $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 để Excel mở đúng tiếng Việt có dấu.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
