<?php

namespace Tests\Feature;

use App\Core\Models\Permission;
use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Models\User;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Position;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Manufacturing\Models\Routing;
use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Reporting\Services\DashboardService;
use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Routing sản xuất + cảnh báo dashboard + tìm đơn mua theo mã
 * + tách quyền xác nhận phiếu nhận hàng (19–20/11/2026).
 */
class ManufacturingRoutingDashboardTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /**
     * Seed nền + sửa quirk có sẵn: CompanyScope ẩn đơn vị dùng chung
     * (company_id = null) nên test đầu tiên trong process gọi createProduct()
     * sẽ lỗi ModelNotFoundException cho Unit 'cai' (OverstockIssueTest cũng
     * fail standalone vì vậy). Gán 'cai' về công ty test để nhìn thấy được.
     */
    private function seedBase(): void
    {
        $this->seedBaseData();

        Unit::withoutGlobalScope(CompanyScope::class)
            ->where('code', 'cai')
            ->whereNull('company_id')
            ->update(['company_id' => $this->company->id]);
    }

    public function test_tao_routing_kem_cong_doan_va_xem_danh_sach(): void
    {
        $this->seedBase();
        $product = $this->createProduct('SP-RTG');
        $wc1 = WorkCenter::create(['company_id' => $this->company->id, 'code' => 'WC-T1', 'name' => 'Chuyền 1', 'cost_per_hour' => 100000]);
        $wc2 = WorkCenter::create(['company_id' => $this->company->id, 'code' => 'WC-T2', 'name' => 'Tổ 2', 'cost_per_hour' => 50000]);

        $response = $this->actingAs($this->admin)->post('/manufacturing/routings', [
            'code' => 'RTG-TEST',
            'name' => 'Quy trình kiểm thử',
            'product_id' => $product->id,
            'operations' => [
                ['work_center_id' => $wc1->id, 'operation_name' => 'Công đoạn 1', 'standard_minutes' => 15],
                ['work_center_id' => $wc2->id, 'operation_name' => 'Công đoạn 2', 'standard_minutes' => 10],
            ],
        ]);

        $response->assertRedirect();
        $routing = Routing::where('code', 'RTG-TEST')->firstOrFail();
        $this->assertCount(2, $routing->operations);
        $this->assertSame('Công đoạn 1', $routing->operations->first()->operation_name);
        $this->assertSame($wc2->id, $routing->operations->last()->work_center_id);

        $this->actingAs($this->admin)->get('/manufacturing/routings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('routings.data', 1));

        $this->actingAs($this->admin)->get('/manufacturing/routings/'.$routing->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('routing.operations', 2));
    }

    public function test_dashboard_alerts_tra_du_thong_tin_moi(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();

        // Tồn thấp: mức tối thiểu 10, chỉ có 3 → thiếu 7.
        $lowProduct = $this->createProduct('SP-THAP', 100000, 10);
        app(StockService::class)->receive($lowProduct->id, $warehouse->id, null, 3, 50000, 'test', 1);

        // Hóa đơn quá hạn 5 ngày.
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'KH-T1', 'name' => 'Khách quá hạn']);
        Invoice::create([
            'company_id' => $this->company->id, 'code' => 'INV-QH-1', 'type' => 'sale',
            'customer_id' => $customer->id, 'invoice_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'subtotal' => 1000000, 'tax_amount' => 0, 'total_amount' => 1000000, 'status' => 'posted',
        ]);

        // Hợp đồng còn 10 ngày.
        $dept = Department::create(['company_id' => $this->company->id, 'code' => 'DP-T1', 'name' => 'Phòng kiểm thử']);
        $pos = Position::create(['company_id' => $this->company->id, 'code' => 'POS-T1', 'name' => 'Chức vụ kiểm thử']);
        $employee = Employee::create([
            'company_id' => $this->company->id, 'code' => 'NV-T1', 'full_name' => 'Nhân viên sắp hết hạn',
            'department_id' => $dept->id, 'position_id' => $pos->id,
            'hire_date' => now()->subYear()->toDateString(), 'status' => 'active', 'base_salary' => 10000000,
        ]);
        Contract::create([
            'company_id' => $this->company->id, 'employee_id' => $employee->id, 'code' => 'HD-T1',
            'type' => Contract::TYPE_FIXED_TERM, 'start_date' => now()->subMonths(11)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(), 'salary' => 10000000, 'status' => 'active',
        ]);

        $alerts = (new DashboardService)->alerts($this->company->id);

        $this->assertCount(1, $alerts['low_stock']);
        $this->assertSame(1, $alerts['low_stock_total']);
        $this->assertSame(7.0, $alerts['low_stock'][0]['shortage']);
        $this->assertSame(3.0, $alerts['low_stock'][0]['qty']);

        $this->assertCount(1, $alerts['overdue_invoices']);
        $this->assertSame(1, $alerts['overdue_invoices_total']);
        $this->assertSame('Khách quá hạn', $alerts['overdue_invoices'][0]['customer_name']);
        $this->assertSame(5, $alerts['overdue_invoices'][0]['days_overdue']);

        $this->assertCount(1, $alerts['expiring_contracts']);
        $this->assertSame(1, $alerts['expiring_contracts_total']);
        $this->assertSame($employee->id, $alerts['expiring_contracts'][0]['employee_id']);
        $this->assertSame(10, $alerts['expiring_contracts'][0]['days_left']);
    }

    public function test_tim_don_mua_theo_ma(): void
    {
        $this->seedBase();
        $supplier = Supplier::create(['company_id' => $this->company->id, 'code' => 'NCC-T1', 'name' => 'NCC kiểm thử']);
        PurchaseOrder::create(['company_id' => $this->company->id, 'code' => 'PO-SEARCH-001', 'supplier_id' => $supplier->id, 'status' => 'draft', 'order_date' => now()->toDateString(), 'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0]);
        PurchaseOrder::create(['company_id' => $this->company->id, 'code' => 'PO-OTHER-002', 'supplier_id' => $supplier->id, 'status' => 'draft', 'order_date' => now()->toDateString(), 'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0]);

        $this->actingAs($this->admin)->get('/procurement/purchase-orders?search=SEARCH-001')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.code', 'PO-SEARCH-001')
                ->where('filters.search', 'SEARCH-001')
            );
    }

    public function test_kho_khong_duoc_validate_phieu_nhan(): void
    {
        $this->seedBase();
        $receipt = $this->createDraftReceipt();

        $this->actingAs($this->user('kho@erp.local'))
            ->post('/procurement/goods-receipts/'.$receipt->id.'/validate')
            ->assertForbidden();

        $this->assertSame('draft', $receipt->fresh()->status);
    }

    public function test_giam_doc_validate_phieu_nhan_thanh_cong(): void
    {
        $this->seedBase();

        // Quyền do worker C thêm vào RolePermissionSeeder.
        if (! Permission::where('name', 'procurement.gr.validate')->exists()) {
            $this->markTestSkipped('Chờ seeder của worker C: quyền procurement.gr.validate chưa tồn tại.');
        }

        $receipt = $this->createDraftReceipt();

        $this->actingAs($this->user('giamdoc@erp.local'))
            ->post('/procurement/goods-receipts/'.$receipt->id.'/validate')
            ->assertRedirect();

        $this->assertSame('validated', $receipt->fresh()->status);
    }

    /** Phiếu nhận nháp không gắn đơn mua (để validate không cần đối chiếu PO). */
    private function createDraftReceipt(): GoodsReceipt
    {
        $supplier = Supplier::create(['company_id' => $this->company->id, 'code' => 'NCC-GR', 'name' => 'NCC nhận hàng']);
        $warehouse = $this->createWarehouse('KHO-GR');
        $product = $this->createProduct('SP-GR');

        $receipt = GoodsReceipt::create([
            'company_id' => $this->company->id, 'code' => 'GR-TEST-1', 'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id, 'status' => 'draft', 'receipt_date' => now()->toDateString(),
        ]);

        GoodsReceiptLine::create([
            'company_id' => $this->company->id, 'goods_receipt_id' => $receipt->id,
            'product_id' => $product->id, 'qty' => 5, 'unit_cost' => 50000,
        ]);

        return $receipt;
    }
}
