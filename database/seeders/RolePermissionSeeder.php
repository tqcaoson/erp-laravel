<?php

namespace Database\Seeders;

use App\Core\Models\Permission;
use App\Core\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Vai trò + quyền chức năng (dạng "module.action").
 * Danh sách quyền này là hợp đồng chung: controller dùng middleware
 * 'permission:...' và Policy dùng đúng các tên dưới đây.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<string, array<string, string>> module => [permission => nhãn] */
    public const PERMISSIONS = [
        'accounting' => [
            'accounting.account.view' => 'Xem hệ thống tài khoản',
            'accounting.account.manage' => 'Quản lý hệ thống tài khoản',
            'accounting.entry.view' => 'Xem bút toán',
            'accounting.entry.create' => 'Tạo bút toán',
            'accounting.entry.post' => 'Ghi sổ bút toán',
            'accounting.entry.reverse' => 'Đảo bút toán',
            'accounting.invoice.view' => 'Xem hóa đơn',
            'accounting.invoice.create' => 'Lập hóa đơn',
            'accounting.invoice.post' => 'Ghi sổ hóa đơn',
            'accounting.payment.view' => 'Xem thu chi',
            'accounting.payment.create' => 'Tạo phiếu thu/chi',
            'accounting.period.manage' => 'Mở/khóa kỳ kế toán',
            'accounting.bank.view' => 'Xem tài khoản ngân hàng',
            'accounting.bank.manage' => 'Quản lý tài khoản ngân hàng',
        ],
        'procurement' => [
            'procurement.supplier.view' => 'Xem nhà cung cấp',
            'procurement.supplier.manage' => 'Quản lý nhà cung cấp',
            'procurement.pr.view' => 'Xem yêu cầu mua hàng',
            'procurement.pr.create' => 'Tạo yêu cầu mua hàng',
            'procurement.pr.approve' => 'Duyệt yêu cầu mua hàng',
            'procurement.po.view' => 'Xem đơn mua hàng',
            'procurement.po.create' => 'Tạo đơn mua hàng',
            'procurement.po.approve' => 'Duyệt đơn mua hàng',
            'procurement.gr.view' => 'Xem phiếu nhận hàng',
            'procurement.gr.create' => 'Tạo phiếu nhận hàng',
            'procurement.gr.validate' => 'Xác nhận phiếu nhận hàng',
            'procurement.rfq.view' => 'Xem báo giá nhà cung cấp',
            'procurement.rfq.manage' => 'Quản lý báo giá nhà cung cấp',
        ],
        'inventory' => [
            'inventory.product.view' => 'Xem sản phẩm',
            'inventory.product.manage' => 'Quản lý sản phẩm',
            'inventory.warehouse.view' => 'Xem kho',
            'inventory.warehouse.manage' => 'Quản lý kho',
            'inventory.stock.view' => 'Xem tồn kho',
            'inventory.stock.move' => 'Nhập/xuất/chuyển kho',
            'inventory.stock.count' => 'Kiểm kê kho',
            'inventory.stock.confirm' => 'Duyệt phiếu kho',
        ],
        'sales' => [
            'sales.customer.view' => 'Xem khách hàng',
            'sales.customer.manage' => 'Quản lý khách hàng',
            'sales.lead.view' => 'Xem đầu mối',
            'sales.lead.manage' => 'Quản lý đầu mối',
            'sales.opportunity.view' => 'Xem cơ hội bán hàng',
            'sales.opportunity.manage' => 'Quản lý cơ hội bán hàng',
            'sales.quotation.view' => 'Xem báo giá',
            'sales.quotation.create' => 'Tạo báo giá',
            'sales.order.view' => 'Xem đơn bán hàng',
            'sales.order.create' => 'Tạo đơn bán hàng',
            'sales.order.confirm' => 'Xác nhận đơn bán hàng',
            'sales.order.approve' => 'Duyệt đơn bán hàng vượt hạn mức',
            'sales.delivery.view' => 'Xem phiếu giao hàng',
            'sales.delivery.validate' => 'Xác nhận giao hàng',
            'sales.price.view' => 'Xem bảng giá',
            'sales.price.manage' => 'Quản lý bảng giá',
        ],
        'manufacturing' => [
            'manufacturing.bom.view' => 'Xem định mức (BOM)',
            'manufacturing.bom.manage' => 'Quản lý định mức (BOM)',
            'manufacturing.mo.view' => 'Xem lệnh sản xuất',
            'manufacturing.mo.create' => 'Tạo lệnh sản xuất',
            'manufacturing.mo.start' => 'Bắt đầu sản xuất',
            'manufacturing.mo.complete' => 'Hoàn thành sản xuất',
            'manufacturing.workcenter.view' => 'Xem trung tâm sản xuất',
            'manufacturing.workcenter.manage' => 'Quản lý trung tâm sản xuất',
            'manufacturing.routing.view' => 'Xem quy trình sản xuất',
            'manufacturing.routing.manage' => 'Quản lý quy trình sản xuất',
        ],
        'hr' => [
            'hr.employee.view' => 'Xem nhân viên',
            'hr.employee.manage' => 'Quản lý nhân viên',
            'hr.attendance.view' => 'Xem chấm công',
            'hr.attendance.manage' => 'Quản lý chấm công',
            'hr.attendance.self' => 'Xem chấm công của mình',
            'hr.leave.view' => 'Xem nghỉ phép',
            'hr.leave.request' => 'Đăng ký nghỉ phép',
            'hr.leave.approve' => 'Duyệt nghỉ phép',
            'hr.leave.self' => 'Xem và đăng ký nghỉ phép của mình',
            'hr.payroll.view' => 'Xem bảng lương',
            'hr.payroll.run' => 'Chạy bảng lương',
            'hr.payroll.approve' => 'Khóa bảng lương',
            'hr.payroll.lock' => 'Khóa bảng lương',
            'hr.payroll.pay' => 'Đánh dấu đã trả lương',
            'hr.payslip.self' => 'Xem phiếu lương của mình',
        ],
        'reporting' => [
            'reporting.dashboard.view' => 'Xem dashboard',
            'reporting.report.view' => 'Xem báo cáo',
            'reporting.report.export' => 'Xuất báo cáo',
        ],
        'core' => [
            'core.user.view' => 'Xem người dùng',
            'core.user.manage' => 'Quản lý người dùng',
            'core.role.manage' => 'Quản lý vai trò/quyền',
            'core.audit.view' => 'Xem nhật ký kiểm tra',
        ],
    ];

    /** @var array<string, list<string>> role => các module hoặc quyền được gán ('*' = tất cả). */
    public const ROLE_PERMISSIONS = [
        'admin' => ['*'],
        'giam_doc' => ['*.view', 'sales.order.approve', 'procurement.po.approve', 'procurement.pr.approve', 'procurement.gr.validate', 'inventory.stock.confirm', 'hr.leave.approve', 'hr.payroll.approve', 'reporting.*'],
        'ke_toan' => ['accounting.*', 'reporting.*', 'sales.order.view', 'sales.customer.view', 'procurement.po.view', 'procurement.supplier.view', 'procurement.supplier.manage', 'hr.payroll.view', 'hr.payroll.pay'],
        // Kho KHÔNG dùng wildcard 'inventory.*': quyền 'inventory.stock.confirm'
        // (duyệt phiếu kho) chỉ giao cho giám đốc để tách nhiệm vụ — thủ kho lập
        // phiếu, giám đốc duyệt. Liệt kê tường minh 7 quyền kho còn lại.
        'kho' => ['inventory.product.view', 'inventory.product.manage', 'inventory.warehouse.view', 'inventory.warehouse.manage', 'inventory.stock.view', 'inventory.stock.move', 'inventory.stock.count', 'procurement.po.view', 'procurement.supplier.view', 'procurement.gr.view', 'procurement.gr.create', 'sales.delivery.view', 'sales.delivery.validate', 'reporting.dashboard.view'],
        // Bán hàng KHÔNG dùng wildcard 'sales.*': quyền 'sales.order.approve'
        // (duyệt đơn bán vượt hạn mức) chỉ giao cho giám đốc — nhân viên bán
        // hàng lập đơn, giám đốc duyệt. Liệt kê tường minh 15 quyền sales còn lại.
        'ban_hang' => ['sales.customer.view', 'sales.customer.manage', 'sales.lead.view', 'sales.lead.manage', 'sales.opportunity.view', 'sales.opportunity.manage', 'sales.quotation.view', 'sales.quotation.create', 'sales.order.view', 'sales.order.create', 'sales.order.confirm', 'sales.delivery.view', 'sales.delivery.validate', 'sales.price.view', 'sales.price.manage', 'inventory.product.view', 'inventory.stock.view', 'reporting.*'],
        // Nhân sự KHÔNG dùng wildcard 'hr.*': quyền 'hr.payroll.approve'
        // (duyệt/khóa bảng lương) giao cho giám đốc, 'hr.payroll.pay'
        // (đánh dấu đã trả lương) giao cho kế toán — nhân sự chạy và khóa
        // bảng lương nhưng không tự duyệt, không tự đánh dấu đã trả.
        // Liệt kê tường minh 13 quyền hr còn lại.
        'nhan_su' => ['hr.employee.view', 'hr.employee.manage', 'hr.attendance.view', 'hr.attendance.manage', 'hr.attendance.self', 'hr.leave.view', 'hr.leave.request', 'hr.leave.approve', 'hr.leave.self', 'hr.payroll.view', 'hr.payroll.run', 'hr.payroll.lock', 'hr.payslip.self', 'reporting.dashboard.view', 'reporting.report.view'],
        'nhan_vien' => ['hr.attendance.self', 'hr.leave.self', 'hr.payslip.self', 'reporting.dashboard.view'],
        // Nhân viên mua hàng: lập yêu cầu/đơn mua, quản lý RFQ và NCC,
        // tạo phiếu nhận — KHÔNG duyệt (po.approve/pr.approve/gr.validate
        // đều thuộc về giám đốc).
        'mua_hang' => ['procurement.pr.view', 'procurement.pr.create', 'procurement.po.view', 'procurement.po.create', 'procurement.rfq.view', 'procurement.rfq.manage', 'procurement.supplier.view', 'procurement.supplier.manage', 'procurement.gr.view', 'procurement.gr.create', 'inventory.product.view', 'inventory.stock.view', 'reporting.dashboard.view'],
        // Nhân viên sản xuất: chạy lệnh sản xuất và xem BOM/quy trình/
        // trung tâm — KHÔNG quản lý BOM/quy trình/trung tâm (thuộc admin).
        'san_xuat' => ['manufacturing.mo.view', 'manufacturing.mo.create', 'manufacturing.mo.start', 'manufacturing.mo.complete', 'manufacturing.bom.view', 'manufacturing.routing.view', 'manufacturing.workcenter.view', 'inventory.stock.view', 'inventory.product.view', 'reporting.dashboard.view'],
    ];

    public const ROLES = [
        'admin' => 'Quản trị hệ thống',
        'giam_doc' => 'Giám đốc',
        'ke_toan' => 'Kế toán',
        'kho' => 'Thủ kho',
        'ban_hang' => 'Nhân viên bán hàng',
        'nhan_su' => 'Nhân sự',
        'nhan_vien' => 'Nhân viên',
        'mua_hang' => 'Nhân viên mua hàng',
        'san_xuat' => 'Nhân viên sản xuất',
    ];

    public function run(): void
    {
        $allPermissions = [];

        foreach (self::PERMISSIONS as $module => $permissions) {
            foreach ($permissions as $name => $label) {
                $allPermissions[$name] = Permission::firstOrCreate(
                    ['name' => $name],
                    ['label' => $label, 'module' => $module],
                );
            }
        }

        foreach (self::ROLES as $name => $label) {
            $role = Role::firstOrCreate(['name' => $name], ['label' => $label]);

            $granted = [];
            foreach (self::ROLE_PERMISSIONS[$name] as $pattern) {
                if ($pattern === '*') {
                    $granted = array_keys($allPermissions);
                    break;
                }

                if (str_ends_with($pattern, '.*')) {
                    $prefix = substr($pattern, 0, -1);
                    foreach (array_keys($allPermissions) as $permissionName) {
                        if (str_starts_with($permissionName, $prefix)) {
                            $granted[] = $permissionName;
                        }
                    }
                } elseif ($pattern === '*.view') {
                    // Chỉ đúng mẫu '*.view' mới nghĩa là tất cả quyền xem.
                    // Trước đây điều kiện là str_ends_with($pattern, '.view')
                    // nên mọi quyền xem cụ thể (vd 'procurement.po.view')
                    // cũng bị hiểu thành wildcard, cấp nhầm toàn bộ quyền
                    // .view cho mọi role có dù chỉ 1 quyền xem cụ thể.
                    foreach (array_keys($allPermissions) as $permissionName) {
                        if (str_ends_with($permissionName, '.view')) {
                            $granted[] = $permissionName;
                        }
                    }
                } else {
                    $granted[] = $pattern;
                }
            }

            $ids = collect(array_unique($granted))
                ->map(fn ($permissionName) => $allPermissions[$permissionName]->id ?? null)
                ->filter()
                ->all();

            // sync() thay cho syncWithoutDetaching(): seeder là nguồn đúng
            // của phân quyền role, chạy lại phải gỡ cả quyền đã cấp nhầm
            // trước đây (vd toàn bộ .view do lỗi wildcard ở trên), chứ
            // không chỉ thêm quyền mới. Lưu ý: quyền gán tay ngoài seeder
            // sẽ bị gỡ khi chạy lại seeder này.
            $role->permissions()->sync($ids);
        }
    }
}
