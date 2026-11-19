<?php

namespace Tests\Feature;

use App\Core\Models\Permission;
use App\Core\Models\Role;
use App\Models\User;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy đợt sửa phân quyền (đợt 2): bán hàng không tự duyệt đơn vượt
 * hạn mức, nhân sự không tự duyệt/trả lương, 2 role mới mua_hang/san_xuat,
 * kế toán quản lý được NCC và đánh dấu đã trả lương.
 */
class RolePermissionRound3Test extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** @return list<string> tên quyền của 1 role. */
    private function rolePermissions(string $roleName): array
    {
        return Role::where('name', $roleName)->firstOrFail()
            ->permissions()->pluck('name')->all();
    }

    /** Tạo đơn bán vượt hạn mức rồi xác nhận để nó rơi vào chờ duyệt. */
    private function createPendingApprovalOrder(): SalesOrder
    {
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'code' => 'KH-TEST-APPROVE',
            'name' => 'Khách kiểm thử duyệt đơn',
            'credit_limit' => 50000000,
            'payment_term_days' => 30,
        ]);

        $order = SalesOrder::create([
            'company_id' => $this->company->id,
            'code' => 'SO-TEST-APPROVE',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 60000000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 60000000,
        ]);

        SalesOrderLine::create([
            'company_id' => $this->company->id,
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 600,
            'unit_price' => 100000,
            'discount_rate' => 0,
            'tax_rate' => 0,
            'line_total' => 60000000,
        ]);

        app(SalesOrderService::class)->confirm($order->fresh());

        $this->assertSame('pending_approval', $order->fresh()->status);

        return $order->fresh();
    }

    public function test_seeder_tạo_đủ_9_role_và_2_quyền_lương_mới(): void
    {
        $this->seedBaseData();

        $this->assertSame(9, Role::count());
        $this->assertSame('Nhân viên mua hàng', Role::where('name', 'mua_hang')->firstOrFail()->label);
        $this->assertSame('Nhân viên sản xuất', Role::where('name', 'san_xuat')->firstOrFail()->label);
        $this->assertTrue(Permission::where('name', 'hr.payroll.lock')->exists());
        $this->assertTrue(Permission::where('name', 'hr.payroll.pay')->exists());
    }

    public function test_ban_hang_không_có_quyền_duyệt_đơn_vượt_hạn_mức(): void
    {
        $this->seedBaseData();

        $permissions = $this->rolePermissions('ban_hang');

        $this->assertNotContains('sales.order.approve', $permissions);
        // Các quyền sales còn lại vẫn đủ (tách wildcard không được làm mất quyền).
        $this->assertContains('sales.order.create', $permissions);
        $this->assertContains('sales.order.confirm', $permissions);
        $this->assertContains('sales.quotation.create', $permissions);
        $this->assertContains('sales.customer.manage', $permissions);
        $this->assertContains('sales.delivery.validate', $permissions);
        $this->assertContains('sales.price.manage', $permissions);
    }

    public function test_nhan_su_không_tự_duyệt_và_trả_lương_nhưng_vẫn_khóa_và_chạy_được(): void
    {
        $this->seedBaseData();

        $permissions = $this->rolePermissions('nhan_su');

        $this->assertNotContains('hr.payroll.approve', $permissions);
        $this->assertNotContains('hr.payroll.pay', $permissions);
        $this->assertContains('hr.payroll.lock', $permissions);
        $this->assertContains('hr.payroll.run', $permissions);
        $this->assertContains('hr.payroll.view', $permissions);
        $this->assertContains('hr.leave.approve', $permissions);
        $this->assertContains('hr.employee.manage', $permissions);
        $this->assertContains('hr.attendance.manage', $permissions);
    }

    public function test_ke_toan_quản_lý_được_ncc_và_đánh_dấu_đã_trả_lương(): void
    {
        $this->seedBaseData();

        $permissions = $this->rolePermissions('ke_toan');

        $this->assertContains('procurement.supplier.manage', $permissions);
        $this->assertContains('hr.payroll.pay', $permissions);

        // Kiểm chứng đầu-cuối: kế toán tạo được NCC qua route web.
        $this->actingAs($this->user('ketoan@erp.local'))
            ->post('/procurement/suppliers', ['code' => 'NCC-KT-1', 'name' => 'NCC do kế toán tạo'])
            ->assertRedirect(route('procurement.suppliers.index'));

        $this->assertDatabaseHas('suppliers', ['code' => 'NCC-KT-1']);
    }

    public function test_role_mua_hang_và_san_xuat_đúng_phạm_vi_quyền(): void
    {
        $this->seedBaseData();

        $muaHang = $this->rolePermissions('mua_hang');
        $this->assertContains('procurement.pr.create', $muaHang);
        $this->assertContains('procurement.po.create', $muaHang);
        $this->assertContains('procurement.rfq.manage', $muaHang);
        $this->assertContains('procurement.supplier.manage', $muaHang);
        $this->assertContains('procurement.gr.create', $muaHang);
        $this->assertContains('inventory.stock.view', $muaHang);
        $this->assertNotContains('procurement.po.approve', $muaHang);
        $this->assertNotContains('procurement.pr.approve', $muaHang);
        $this->assertNotContains('procurement.gr.validate', $muaHang);

        $sanXuat = $this->rolePermissions('san_xuat');
        $this->assertContains('manufacturing.mo.create', $sanXuat);
        $this->assertContains('manufacturing.mo.start', $sanXuat);
        $this->assertContains('manufacturing.mo.complete', $sanXuat);
        $this->assertContains('manufacturing.bom.view', $sanXuat);
        $this->assertContains('manufacturing.routing.view', $sanXuat);
        $this->assertContains('manufacturing.workcenter.view', $sanXuat);
        $this->assertNotContains('manufacturing.bom.manage', $sanXuat);
        $this->assertNotContains('manufacturing.routing.manage', $sanXuat);
        $this->assertNotContains('manufacturing.workcenter.manage', $sanXuat);
    }

    public function test_tài_khoản_demo_mua_hang_và_san_xuat_đăng_nhập_được(): void
    {
        $this->seedBaseData();

        $this->post('/login', ['email' => 'muahang@erp.local', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user('muahang@erp.local'));

        $this->post('/logout');

        $this->post('/login', ['email' => 'sanxuat@erp.local', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user('sanxuat@erp.local'));
    }

    public function test_ban_hang_không_duyệt_được_đơn_vượt_hạn_mức(): void
    {
        $this->seedBaseData();
        $order = $this->createPendingApprovalOrder();

        $this->actingAs($this->user('banhang@erp.local'))
            ->post("/sales/orders/{$order->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending_approval', $order->fresh()->status);
    }

    public function test_giam_doc_duyệt_được_đơn_vượt_hạn_mức(): void
    {
        $this->seedBaseData();
        // Đơn tạo trực tiếp qua model (không phải do giám đốc tạo) nên
        // qua được cả rule maker-checker nếu worker B đã thêm.
        $order = $this->createPendingApprovalOrder();

        $this->actingAs($this->user('giamdoc@erp.local'))
            ->post("/sales/orders/{$order->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_mua_hang_tạo_được_pr_po_và_quản_lý_ncc_nhưng_không_duyệt_po(): void
    {
        $this->seedBaseData();
        $muaHang = $this->user('muahang@erp.local');
        $product = $this->createProduct();

        // Quản lý NCC: tạo được.
        $this->actingAs($muaHang)
            ->post('/procurement/suppliers', ['code' => 'NCC-MH-1', 'name' => 'NCC kiểm thử mua hàng'])
            ->assertRedirect(route('procurement.suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['code' => 'NCC-MH-1']);
        $supplier = Supplier::where('code', 'NCC-MH-1')->firstOrFail();

        // Tạo yêu cầu mua.
        $this->actingAs($muaHang)
            ->post('/procurement/purchase-requests', [
                'lines' => [['product_id' => $product->id, 'qty' => 5]],
            ])
            ->assertRedirect();
        $this->assertDatabaseCount('purchase_requests', 1);

        // Tạo đơn mua.
        $this->actingAs($muaHang)
            ->post('/procurement/purchase-orders', [
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [['product_id' => $product->id, 'qty' => 5, 'unit_price' => 50000]],
            ])
            ->assertRedirect();
        $this->assertDatabaseCount('purchase_orders', 1);

        // Duyệt đơn mua thì bị chặn (quyền của giám đốc).
        $poId = \App\Modules\Procurement\Models\PurchaseOrder::firstOrFail()->id;
        $this->actingAs($muaHang)
            ->post("/procurement/purchase-orders/{$poId}/approve")
            ->assertForbidden();
    }

    public function test_san_xuat_xem_được_danh_sách_lệnh_sản_xuất_nhưng_không_quản_lý_bom(): void
    {
        $this->seedBaseData();
        $sanXuat = $this->user('sanxuat@erp.local');

        $this->actingAs($sanXuat)->get('/manufacturing/orders')->assertOk();
        $this->actingAs($sanXuat)->get('/manufacturing/boms')->assertOk();
        $this->actingAs($sanXuat)->get('/manufacturing/boms/create')->assertForbidden();
    }
}
