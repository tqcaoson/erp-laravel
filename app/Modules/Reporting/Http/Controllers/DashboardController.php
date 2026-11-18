<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Modules\Reporting\Services\DashboardService;
use Inertia\Inertia;

/**
 * Dashboard chính — ROUTE QUAN TRỌNG NHẤT của hệ thống: trang login
 * redirect về đây và sidebar trỏ tới đây (GET /dashboard, name dashboard).
 *
 * Dashboard phải LUÔN mở được: mọi khối số liệu đều có try/catch trong
 * DashboardService, thiếu bảng/dữ liệu của module khác thì hiển thị 0.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index()
    {
        $companyId = CurrentCompany::id();

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

        return Inertia::render('Dashboard', [
            'kpi' => $this->dashboard->kpi($companyId),
            'alerts' => $this->dashboard->alerts($companyId),
            'monthlyRevenue' => $this->dashboard->monthlyRevenue($companyId),
        ]);
    }
}
