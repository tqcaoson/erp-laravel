<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Modules\Reporting\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Dashboard chính — ROUTE QUAN TRỌNG NHẤT của hệ thống: trang login
 * redirect về đây và sidebar trỏ tới đây (GET /dashboard, name dashboard).
 *
 * Dashboard phải LUÔN mở được: mọi khối số liệu đều có try/catch trong
 * DashboardService, thiếu bảng/dữ liệu của module khác thì hiển thị 0.
 * Nhưng mỗi role chỉ nhận số liệu thuộc module của mình — số liệu được
 * lọc theo quyền trong index() trước khi gửi cho giao diện.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request)
    {
        $companyId = CurrentCompany::id();
        $user = $request->user();

        // Chưa chọn công ty (tài khoản mới chưa thuộc công ty nào):
        // vẫn render trang với số 0 thay vì lỗi.
        if (! $companyId) {
            return Inertia::render('Dashboard', [
                'kpi' => [
                    'revenue_this_month' => 0, 'orders_this_month' => 0,
                    'stock_value' => 0, 'receivables' => 0,
                    'active_employees' => 0, 'pending_leave_requests' => 0,
                ],
                'alerts' => ['low_stock' => [], 'overdue_invoices' => [], 'expiring_contracts' => []],
                'monthlyRevenue' => [],
            ]);
        }

        $kpi = $this->dashboard->kpi($companyId);
        $alerts = $this->dashboard->alerts($companyId);
        $monthlyRevenue = $this->dashboard->monthlyRevenue($companyId);

        // Lọc số liệu theo quyền: dashboard mở cho mọi user đã đăng nhập,
        // nhưng mỗi role chỉ nhận số liệu thuộc module của mình (tránh lộ
        // doanh thu/công nợ/nhân sự sang role khác). Cache KPI vẫn dùng
        // chung theo công ty; việc lọc diễn ra trên bản sao sau khi lấy cache.
        if (! $user->hasPermission('sales.order.view') && ! $user->hasPermission('reporting.report.view')) {
            unset($kpi['revenue_this_month'], $kpi['orders_this_month']);
            $monthlyRevenue = [];
        }
        if (! $user->hasPermission('inventory.stock.view')) {
            unset($kpi['stock_value']);
            $alerts['low_stock'] = [];
        }
        if (! $user->hasPermission('accounting.invoice.view')) {
            unset($kpi['receivables']);
            $alerts['overdue_invoices'] = [];
        }
        if (! $user->hasPermission('hr.employee.view')) {
            unset($kpi['active_employees']);
            $alerts['expiring_contracts'] = [];
        }
        if (! $user->hasPermission('hr.leave.view')) {
            unset($kpi['pending_leave_requests']);
        }

        return Inertia::render('Dashboard', [
            'kpi' => $kpi,
            'alerts' => $alerts,
            'monthlyRevenue' => $monthlyRevenue,
        ]);
    }
}
