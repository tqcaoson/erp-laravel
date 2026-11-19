<?php

namespace Tests\Feature;

use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\BomLine;
use App\Modules\Manufacturing\Models\ManufacturingOrder;
use App\Modules\Manufacturing\Models\ProductionLog;
use App\Modules\Manufacturing\Models\Routing;
use App\Modules\Manufacturing\Models\RoutingOperation;
use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\ManufacturingOrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy lỗi 5 (đợt 2, worker D):
 * - Ghi nhận tiến độ từng phần (logProgress) + hoàn thành lần cuối không
 *   xuất trùng nguyên liệu.
 * - Đổi trạng thái công đoạn lưu được actual_minutes.
 * - Route POST log-progress + phân quyền.
 */
class ProductionProgressTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    private Warehouse $warehouse;

    private Product $material;

    private Product $finished;

    private ManufacturingOrderService $service;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseData();

        // Quirk có sẵn: CompanyScope ẩn đơn vị dùng chung (company_id = null)
        // nên gán 'cai' về công ty test để createProduct() nhìn thấy được.
        Unit::withoutGlobalScope(CompanyScope::class)
            ->where('code', 'cai')
            ->whereNull('company_id')
            ->update(['company_id' => $this->company->id]);

        $this->service = app(ManufacturingOrderService::class);
        $this->stock = app(StockService::class);
        $this->warehouse = $this->createWarehouse();
        $this->material = $this->createProduct('NL-TIEN-DO');
        $this->finished = $this->createProduct('TP-TIEN-DO');

        // Tồn nguyên liệu 1000, giá vốn 10.000 ₫.
        $this->stock->receive($this->material->id, $this->warehouse->id, null, 1000, 10000, 'test', 1);

        // BOM: 1 thành phẩm cần 2 nguyên liệu, không hao hụt.
        $bom = Bom::create([
            'company_id' => $this->company->id, 'code' => 'BOM-TD', 'product_id' => $this->finished->id,
            'version' => 1, 'is_active' => true,
        ]);
        $unit = Unit::query()->where('code', 'cai')->firstOrFail();
        BomLine::create([
            'company_id' => $this->company->id, 'bom_id' => $bom->id,
            'component_product_id' => $this->material->id, 'qty' => 2,
            'unit_id' => $unit->id, 'scrap_rate' => 0,
        ]);

        $this->bomId = $bom->id;
    }

    private int $bomId;

    /** Lệnh sản xuất 100 cái đang thực hiện (đã confirm + start). */
    private function makeInProgressOrder(?int $routingId = null): ManufacturingOrder
    {
        $order = $this->service->create([
            'product_id' => $this->finished->id,
            'bom_id' => $this->bomId,
            'routing_id' => $routingId,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 100,
        ]);

        $this->service->confirm($order);

        return $this->service->start($order->fresh());
    }

    private function available(Product $product): float
    {
        return $this->stock->availableQty($product->id, $this->warehouse->id);
    }

    public function test_log_progress_hai_lan_roi_complete_khong_xuat_trung_nguyen_lieu(): void
    {
        $order = $this->makeInProgressOrder();

        // Lần 1: 40 cái → xuất 80 nguyên liệu, nhập 40 thành phẩm, vẫn in_progress.
        $result = $this->service->logProgress($order, 40);
        $this->assertSame('in_progress', $result['order']->status);
        $this->assertEquals(40, (float) $result['order']->produced_qty);
        $this->assertEquals(920, $this->available($this->material));
        $this->assertEquals(40, $this->available($this->finished));
        $this->assertEquals(20000, $result['unit_cost']); // 2 nguyên liệu × 10.000 ₫.

        // Lần 2: 30 cái → xuất thêm 60, nhập thêm 30, vẫn in_progress.
        $result = $this->service->logProgress($result['order'], 30);
        $this->assertSame('in_progress', $result['order']->status);
        $this->assertEquals(70, (float) $result['order']->produced_qty);
        $this->assertEquals(860, $this->available($this->material));
        $this->assertEquals(70, $this->available($this->finished));

        // Lần chốt: 30 cái còn lại → chỉ xuất 60 nguyên liệu cho 30 cái này
        // (không xuất lại 140 đã xuất ở 2 lần trước), tổng đúng 200.
        $result = $this->service->complete($result['order'], 30);
        $this->assertSame('completed', $result['order']->status);
        $this->assertEquals(100, (float) $result['order']->produced_qty);
        $this->assertEquals(800, $this->available($this->material));
        $this->assertEquals(100, $this->available($this->finished));

        // 3 lần ghi = 3 nhật ký, tổng sản lượng 100.
        $this->assertCount(3, ProductionLog::where('manufacturing_order_id', $order->id)->get());
        $this->assertEquals(100, (float) ProductionLog::where('manufacturing_order_id', $order->id)->sum('qty_produced'));
    }

    public function test_log_progress_tinh_ca_phe_pham_khi_xuat_nguyen_lieu(): void
    {
        $order = $this->makeInProgressOrder();

        // 10 thành phẩm + 5 phế phẩm → tiêu hao nguyên liệu cho 15 cái = 30.
        $result = $this->service->logProgress($order, 10, 5);
        $this->assertEquals(10, (float) $result['order']->produced_qty);
        $this->assertEquals(970, $this->available($this->material));
        $this->assertEquals(10, $this->available($this->finished));
        $this->assertSame('in_progress', $result['order']->status);
    }

    public function test_log_progress_thieu_ton_thi_loi_ro_rang_khong_ton_am_khong_ghi_du_lieu(): void
    {
        $order = $this->makeInProgressOrder();

        // Sau khi bắt đầu, xuất bớt nguyên liệu đi nơi khác, chỉ còn 50.
        $this->stock->issue($this->material->id, $this->warehouse->id, null, 950, 'test', 1);
        $this->assertEquals(50, $this->available($this->material));

        // Ghi 40 cái cần 80 nguyên liệu → thiếu, phải ném lỗi rõ ràng.
        try {
            $this->service->logProgress($order, 40);
            $this->fail('Phải ném DomainException khi thiếu tồn kho.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Không đủ tồn kho', $e->getMessage());
        }

        // Không ghi dở dang: không nhật ký, produced_qty vẫn 0, tồn vẫn 50.
        $this->assertSame(0, ProductionLog::where('manufacturing_order_id', $order->id)->count());
        $this->assertEquals(0, (float) $order->fresh()->produced_qty);
        $this->assertEquals(50, $this->available($this->material));
        $this->assertEquals(0, $this->available($this->finished));
    }

    public function test_khong_complete_duoc_hai_lan(): void
    {
        $order = $this->makeInProgressOrder();
        $this->service->complete($order, 100);

        $this->expectException(DomainException::class);
        $this->service->complete($order->fresh(), 1);
    }

    public function test_route_log_progress_qua_http_va_validate(): void
    {
        $order = $this->makeInProgressOrder();

        $this->actingAs($this->admin)
            ->post("/manufacturing/orders/{$order->id}/log-progress", ['qty_produced' => 25, 'qty_scrap' => 0])
            ->assertRedirect();

        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertEquals(25, (float) $order->fresh()->produced_qty);
        $this->assertEquals(950, $this->available($this->material));

        // Số lượng 0 → lỗi validate, không ghi.
        $this->actingAs($this->admin)
            ->post("/manufacturing/orders/{$order->id}/log-progress", ['qty_produced' => 0])
            ->assertSessionHasErrors('qty_produced');
        $this->assertEquals(25, (float) $order->fresh()->produced_qty);
    }

    public function test_route_log_progress_yeu_cau_quyen_complete(): void
    {
        $order = $this->makeInProgressOrder();
        $nhanVien = User::where('email', 'nhanvien@erp.local')->firstOrFail();

        $this->actingAs($nhanVien)
            ->post("/manufacturing/orders/{$order->id}/log-progress", ['qty_produced' => 10])
            ->assertForbidden();

        $this->assertEquals(0, (float) $order->fresh()->produced_qty);
    }

    public function test_doi_trang_thai_cong_doan_luu_duoc_actual_minutes(): void
    {
        $wc = WorkCenter::create([
            'company_id' => $this->company->id, 'code' => 'WC-TD', 'name' => 'Chuyền kiểm thử', 'cost_per_hour' => 100000,
        ]);
        $routing = Routing::create([
            'company_id' => $this->company->id, 'code' => 'RTG-TD', 'name' => 'Quy trình kiểm thử', 'product_id' => $this->finished->id,
        ]);
        RoutingOperation::create([
            'company_id' => $this->company->id, 'routing_id' => $routing->id, 'work_center_id' => $wc->id,
            'sequence' => 1, 'operation_name' => 'Công đoạn 1', 'standard_minutes' => 30,
        ]);

        $order = $this->makeInProgressOrder($routing->id);
        $workOrder = WorkOrder::where('manufacturing_order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $workOrder->status);
        $this->assertNull($workOrder->actual_minutes);

        // Gửi kèm actual_minutes khi đổi trạng thái (UI gửi kèm từ ô nhập mới).
        $this->actingAs($this->admin)
            ->patch("/manufacturing/work-orders/{$workOrder->id}/status", ['status' => 'in_progress', 'actual_minutes' => 45])
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('in_progress', $workOrder->status);
        $this->assertEquals(45, (float) $workOrder->actual_minutes);

        // User không có quyền mo.start thì 403.
        $nhanVien = User::where('email', 'nhanvien@erp.local')->firstOrFail();
        $this->actingAs($nhanVien)
            ->patch("/manufacturing/work-orders/{$workOrder->id}/status", ['status' => 'completed', 'actual_minutes' => 50])
            ->assertForbidden();
        $this->assertSame('in_progress', $workOrder->fresh()->status);
    }
}
