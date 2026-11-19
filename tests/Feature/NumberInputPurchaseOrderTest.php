<?php

namespace Tests\Feature;

use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Procurement\Models\Rfq;
use App\Modules\Procurement\Models\RfqLine;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy lỗi 11 (đợt 2, worker D): backend nhận số nguyên thuần từ
 * component NumberInput (20.000/20,000/20 000 đều emit 20000) và lưu đúng.
 * Kèm test prefill đơn mua từ RFQ đã chấp nhận (prop fromRfq).
 */
class NumberInputPurchaseOrderTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

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
    }

    private function createSupplier(): Supplier
    {
        return Supplier::create([
            'company_id' => $this->company->id, 'code' => 'NCC-SO', 'name' => 'NCC kiểm thử số',
        ]);
    }

    public function test_tao_don_mua_voi_so_nguyen_luu_dung_gia_tri(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct('SP-SO-1');

        // NumberInput emit số thuần: qty 10, unit_price 20000 (dù người dùng
        // gõ "20.000", "20,000" hay "20 000" trên giao diện).
        $this->actingAs($this->admin)
            ->post('/procurement/purchase-orders', [
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [
                    ['product_id' => $product->id, 'qty' => 10, 'unit_price' => 20000],
                ],
            ])
            ->assertRedirect();

        $line = PurchaseOrderLine::firstOrFail();
        $this->assertEquals(10, (float) $line->qty);
        $this->assertEquals(20000, (float) $line->unit_price);
        $this->assertEquals(200000, (float) $line->line_total);

        $order = PurchaseOrder::firstOrFail();
        $this->assertEquals(200000, (float) $order->subtotal);
        $this->assertEquals(200000, (float) $order->total_amount);
    }

    public function test_tao_don_mua_voi_so_luong_thap_phan_luu_dung(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct('SP-SO-2');

        $this->actingAs($this->admin)
            ->post('/procurement/purchase-orders', [
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [
                    ['product_id' => $product->id, 'qty' => 2.5, 'unit_price' => 19500],
                ],
            ])
            ->assertRedirect();

        $line = PurchaseOrderLine::firstOrFail();
        $this->assertEquals(2.5, (float) $line->qty);
        $this->assertEquals(19500, (float) $line->unit_price);
        $this->assertEquals(48750, (float) $line->line_total);
    }

    public function test_tao_don_mua_tu_rfq_duoc_dien_san_nha_cung_cap_va_gia_chao(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct('SP-SO-3');

        $rfq = Rfq::create([
            'company_id' => $this->company->id, 'code' => 'RFQ-TD-1',
            'supplier_id' => $supplier->id, 'status' => 'accepted',
        ]);
        RfqLine::create([
            'company_id' => $this->company->id, 'rfq_id' => $rfq->id,
            'product_id' => $product->id, 'qty' => 5, 'quoted_price' => 25500,
        ]);

        // Trang tạo đơn mua nhận prop fromRfq để form điền sẵn (worker B làm
        // controller, worker D nối prop vào form Create.vue).
        $this->actingAs($this->admin)->get('/procurement/purchase-orders/create?rfq_id='.$rfq->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('fromRfq.id', $rfq->id)
                ->where('fromRfq.supplier_id', $supplier->id)
                ->where('fromRfq.lines.0.product_id', $product->id)
                ->where('fromRfq.lines.0.qty', '5.0000')
                ->where('fromRfq.lines.0.unit_price', '25500.00')
            );

        // RFQ chưa chấp nhận thì không prefill.
        $rfq->update(['status' => 'sent']);
        $this->actingAs($this->admin)->get('/procurement/purchase-orders/create?rfq_id='.$rfq->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('fromRfq', null));
    }
}
