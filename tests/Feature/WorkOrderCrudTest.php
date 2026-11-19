<?php

namespace Tests\Feature;

use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy: thêm/sửa/xóa công đoạn sản xuất (theo yêu cầu 20/11/2026).
 * - Chỉ thêm/sửa/xóa khi lệnh đang thực hiện (in_progress).
 * - Thứ tự duy nhất trong mỗi lệnh.
 * - Không xóa công đoạn đã hoàn thành hoặc đã có nhật ký sản xuất.
 * - Cần quyền manufacturing.workorder.manage (san_xuat có, giam_doc không).
 */
class WorkOrderCrudTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    private Warehouse $warehouse;

    private Product $material;

    private Product $finished;

    private WorkCenter $workCenter;

    private int $bomId;

    private int $routingId;

    private ManufacturingOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseData();

        Unit::withoutGlobalScope(CompanyScope::class)
            ->where('code', 'cai')
            ->whereNull('company_id')
            ->update(['company_id' => $this->company->id]);

        $this->service = app(ManufacturingOrderService::class);
        $this->warehouse = $this->createWarehouse();
        $this->material = $this->createProduct('NL-CONG-DOAN');
        $this->finished = $this->createProduct('TP-CONG-DOAN');

        app(StockService::class)->receive($this->material->id, $this->warehouse->id, null, 1000, 10000, 'test', 1);

        $bom = Bom::create([
            'company_id' => $this->company->id, 'code' => 'BOM-CD', 'product_id' => $this->finished->id,
            'version' => 1, 'is_active' => true,
        ]);
        $unit = Unit::query()->where('code', 'cai')->firstOrFail();
        BomLine::create([
            'company_id' => $this->company->id, 'bom_id' => $bom->id,
            'component_product_id' => $this->material->id, 'qty' => 2,
            'unit_id' => $unit->id, 'scrap_rate' => 0,
        ]);
        $this->bomId = $bom->id;

        $this->workCenter = WorkCenter::create([
            'company_id' => $this->company->id, 'code' => 'WC-CD',
            'name' => 'Chuyền test', 'cost_per_hour' => 100000, 'is_active' => true,
        ]);

        $routing = Routing::create([
            'company_id' => $this->company->id, 'code' => 'RTG-CD',
            'name' => 'Quy trình test', 'product_id' => $this->finished->id,
        ]);
        foreach ([1, 2] as $seq) {
            RoutingOperation::create([
                'company_id' => $this->company->id, 'routing_id' => $routing->id,
                'sequence' => $seq, 'work_center_id' => $this->workCenter->id,
                'operation_name' => 'Công đoạn '.$seq, 'standard_minutes' => 10,
            ]);
        }
        $this->routingId = $routing->id;
    }

    private function makeDraftOrder(): ManufacturingOrder
    {
        return $this->service->create([
            'product_id' => $this->finished->id,
            'bom_id' => $this->bomId,
            'routing_id' => $this->routingId,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 100,
        ]);
    }

    /** Lệnh đang thực hiện, có sẵn 2 công đoạn từ routing (sequence 1, 2). */
    private function makeInProgressOrder(): ManufacturingOrder
    {
        $order = $this->makeDraftOrder();
        $this->service->confirm($order);

        return $this->service->start($order->fresh());
    }

    private function validPayload(ManufacturingOrder $order, array $overrides = []): array
    {
        return array_merge([
            'manufacturing_order_id' => $order->id,
            'operation_name' => 'Kiểm tra chất lượng',
            'work_center_id' => $this->workCenter->id,
            'sequence' => 3,
            'planned_minutes' => 15,
            'actual_minutes' => null,
        ], $overrides);
    }

    public function test_san_xuat_them_duoc_cong_doan_cho_lenh_dang_thuc_hien(): void
    {
        $order = $this->makeInProgressOrder();
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->post(route('manufacturing.work-orders.store'), $this->validPayload($order))
            ->assertRedirect();

        $created = WorkOrder::query()->where('manufacturing_order_id', $order->id)->where('sequence', 3)->first();
        $this->assertNotNull($created);
        $this->assertSame('Kiểm tra chất lượng', $created->operation_name);
        $this->assertSame('pending', $created->status);
        $this->assertNotEmpty($created->code);
    }

    public function test_khong_them_duoc_cong_doan_cho_lenh_nhap(): void
    {
        $order = $this->makeDraftOrder();
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->post(route('manufacturing.work-orders.store'), $this->validPayload($order))
            ->assertSessionHas('error');

        $this->assertSame(0, WorkOrder::query()->where('manufacturing_order_id', $order->id)->count());
    }

    public function test_giam_doc_khong_them_duoc_cong_doan(): void
    {
        $order = $this->makeInProgressOrder();
        $user = $this->createUserWithRole('gd-congdoan@erp.local', 'giam_doc');

        $this->actingAs($user)
            ->post(route('manufacturing.work-orders.store'), $this->validPayload($order))
            ->assertForbidden();
    }

    public function test_thu_tu_trung_trong_lenh_thi_bao_loi(): void
    {
        $order = $this->makeInProgressOrder();
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->post(route('manufacturing.work-orders.store'), $this->validPayload($order, ['sequence' => 1]))
            ->assertSessionHasErrors('sequence');

        $this->assertSame(2, WorkOrder::query()->where('manufacturing_order_id', $order->id)->count());
    }

    public function test_san_xuat_sua_duoc_cong_doan(): void
    {
        $order = $this->makeInProgressOrder();
        $workOrder = $order->workOrders()->where('sequence', 1)->firstOrFail();
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->put(route('manufacturing.work-orders.update', $workOrder), [
                'operation_name' => 'Ép cam (đã sửa)',
                'work_center_id' => $this->workCenter->id,
                'sequence' => 1,
                'planned_minutes' => 25,
                'actual_minutes' => 18,
            ])
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('Ép cam (đã sửa)', $workOrder->operation_name);
        $this->assertEquals(25, (float) $workOrder->planned_minutes);
        $this->assertEquals(18, (float) $workOrder->actual_minutes);
    }

    public function test_khong_sua_duoc_cong_doan_khi_lenh_da_xong(): void
    {
        $order = $this->makeInProgressOrder();
        $this->service->complete($order->fresh(), 100);
        $workOrder = $order->workOrders()->where('sequence', 1)->firstOrFail();
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->put(route('manufacturing.work-orders.update', $workOrder), [
                'operation_name' => 'Sửa sau khi xong',
                'work_center_id' => $this->workCenter->id,
                'sequence' => 1,
                'planned_minutes' => 25,
                'actual_minutes' => null,
            ])
            ->assertSessionHas('error');

        $this->assertSame('Công đoạn 1', $workOrder->fresh()->operation_name);
    }

    public function test_san_xuat_xoa_duoc_cong_doan_cho_chua_co_nhat_ky(): void
    {
        $order = $this->makeInProgressOrder();
        $workOrder = $order->workOrders()->where('sequence', 2)->firstOrFail();
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->delete(route('manufacturing.work-orders.destroy', $workOrder))
            ->assertRedirect();

        $this->assertNull(WorkOrder::find($workOrder->id));
    }

    public function test_khong_xoa_duoc_cong_doan_da_co_nhat_ky_san_xuat(): void
    {
        $order = $this->makeInProgressOrder();
        $workOrder = $order->workOrders()->where('sequence', 1)->firstOrFail();
        ProductionLog::create([
            'company_id' => $this->company->id,
            'manufacturing_order_id' => $order->id,
            'work_order_id' => $workOrder->id,
            'product_id' => $this->finished->id,
            'qty_produced' => 10, 'qty_scrap' => 0,
            'logged_at' => now(),
        ]);
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->delete(route('manufacturing.work-orders.destroy', $workOrder))
            ->assertSessionHas('error');

        $this->assertNotNull(WorkOrder::find($workOrder->id));
    }

    public function test_khong_xoa_duoc_cong_doan_da_hoan_thanh(): void
    {
        $order = $this->makeInProgressOrder();
        $workOrder = $order->workOrders()->where('sequence', 1)->firstOrFail();
        $workOrder->update(['status' => 'completed']);
        $user = $this->createUserWithRole('sx-congdoan@erp.local', 'san_xuat');

        $this->actingAs($user)
            ->delete(route('manufacturing.work-orders.destroy', $workOrder))
            ->assertSessionHas('error');

        $this->assertNotNull(WorkOrder::find($workOrder->id));
    }

    public function test_giam_doc_khong_xoa_duoc_cong_doan(): void
    {
        $order = $this->makeInProgressOrder();
        $workOrder = $order->workOrders()->where('sequence', 2)->firstOrFail();
        $user = $this->createUserWithRole('gd-congdoan@erp.local', 'giam_doc');

        $this->actingAs($user)
            ->delete(route('manufacturing.work-orders.destroy', $workOrder))
            ->assertForbidden();

        $this->assertNotNull(WorkOrder::find($workOrder->id));
    }
}
