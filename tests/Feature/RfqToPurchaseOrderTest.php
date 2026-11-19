<?php

namespace Tests\Feature;

use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Modules\Procurement\Models\Rfq;
use App\Modules\Procurement\Models\RfqLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\Feature\Concerns\CreatesProcurementFixtures;
use Tests\TestCase;

/**
 * Chuyển RFQ đã chấp nhận thành đơn mua (prefill supplier/lines/quoted_price)
 * + cột "Tiến độ nhận" ở danh sách đơn mua.
 */
class RfqToPurchaseOrderTest extends TestCase
{
    use CreatesErpFixtures {
        seedBaseData as traitSeedBaseData;
    }

    use CreatesProcurementFixtures;
    use RefreshDatabase;

    protected function seedBaseData(): void
    {
        $this->traitSeedBaseData();

        Unit::withoutGlobalScope(CompanyScope::class)
            ->whereNull('company_id')
            ->update(['company_id' => $this->company->id]);
    }

    protected function createRfq(string $status = 'accepted'): Rfq
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $rfq = Rfq::create([
            'company_id' => $this->company->id,
            'code' => 'RFQ-'.uniqid(),
            'supplier_id' => $supplier->id,
            'status' => $status,
        ]);

        RfqLine::create([
            'company_id' => $this->company->id,
            'rfq_id' => $rfq->id,
            'product_id' => $product->id,
            'qty' => 10,
            'quoted_price' => 45000,
        ]);

        return $rfq->fresh();
    }

    public function test_tao_don_mua_tu_rfq_da_chap_nhan_dien_san_dung(): void
    {
        $this->seedBaseData();
        $rfq = $this->createRfq('accepted');
        $line = $rfq->lines->firstOrFail();

        $this->actingAs($this->admin)
            ->get('/procurement/purchase-orders/create?rfq_id='.$rfq->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('fromRfq.id', $rfq->id)
                ->where('fromRfq.supplier_id', $rfq->supplier_id)
                ->has('fromRfq.lines', 1)
                ->where('fromRfq.lines.0.product_id', $line->product_id)
                ->where('fromRfq.lines.0.qty', fn ($v) => (float) $v === 10.0)
                ->where('fromRfq.lines.0.quoted_price', fn ($v) => (float) $v === 45000.0)
                ->where('fromRfq.lines.0.unit_price', fn ($v) => (float) $v === 45000.0));
    }

    public function test_rfq_chua_chap_nhan_khong_dien_san(): void
    {
        $this->seedBaseData();
        $rfq = $this->createRfq('received');

        $this->actingAs($this->admin)
            ->get('/procurement/purchase-orders/create?rfq_id='.$rfq->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('fromRfq', null));
    }

    public function test_khong_co_rfq_id_thi_from_rfq_null(): void
    {
        $this->seedBaseData();

        $this->actingAs($this->admin)
            ->get('/procurement/purchase-orders/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('fromRfq', null)
                ->where('fromRequest', null));
    }

    public function test_danh_sach_don_mua_co_du_lieu_tien_do_nhan(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        // Đơn mua 100, đã nhận 60 → tiến độ 60/100.
        $order = $this->createApprovedPurchaseOrder($supplier, $product, 100, 50000);
        $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 60, 50000);

        $this->actingAs($this->admin)
            ->get('/procurement/purchase-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.lines.0.qty', fn ($v) => (float) $v === 100.0)
                ->where('orders.data.0.lines.0.received_qty', fn ($v) => (float) $v === 60.0));
    }

    public function test_trang_chi_tiet_rfq_mo_duoc(): void
    {
        $this->seedBaseData();
        $rfq = $this->createRfq('accepted');

        $this->actingAs($this->admin)
            ->get('/procurement/rfqs/'.$rfq->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rfq.status', 'accepted')
                ->has('rfq.lines', 1));
    }
}
