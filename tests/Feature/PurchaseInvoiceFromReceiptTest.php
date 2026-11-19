<?php

namespace Tests\Feature;

use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\InvoiceService;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Services\GoodsReceiptService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\Feature\Concerns\CreatesProcurementFixtures;
use Tests\TestCase;

/**
 * Lập hóa đơn mua từ phiếu nhận hàng (chống lập trùng qua invoiced_qty)
 * + hạch toán 335 khi ghi sổ + hóa đơn mua nhập tay vẫn ghi 156.
 */
class PurchaseInvoiceFromReceiptTest extends TestCase
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

    public function test_lap_hoa_don_mua_tu_phieu_nhan_da_xac_nhan(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        $invoiceId = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());

        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertSame('purchase', $invoice->type);
        $this->assertSame($supplier->id, $invoice->supplier_id);
        $this->assertSame(GoodsReceipt::class, $invoice->source_type);
        $this->assertSame($receipt->id, $invoice->source_id);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame(500000.0, (float) $invoice->subtotal);
        $this->assertSame(0.0, (float) $invoice->tax_amount);
        $this->assertSame(500000.0, (float) $invoice->total_amount);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame(10.0, (float) $invoice->lines->first()->qty);
        $this->assertSame(50000.0, (float) $invoice->lines->first()->unit_price);

        // Dòng phiếu nhận đã ghi nhận đủ phần vừa lập hóa đơn.
        $this->assertSame(10.0, (float) $receipt->fresh()->lines->first()->invoiced_qty);
    }

    public function test_lap_hoa_don_lan_2_khong_lap_trung_phan_da_lap(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('không còn phần nào chưa lập hóa đơn');
        app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());
    }

    public function test_lap_hoa_don_tung_phan_theo_tung_phieu_nhan(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        // Đơn mua 100, nhận 2 đợt 60 + 40.
        $order = $this->createApprovedPurchaseOrder($supplier, $product, 100, 50000);
        $receipt1 = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 60, 50000);
        $receipt2 = $this->createValidatedGoodsReceipt($order->fresh(), $supplier, $warehouse, $product, 40, 50000);

        $invoiceId1 = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt1->fresh());
        $invoiceId2 = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt2->fresh());

        $this->assertNotSame($invoiceId1, $invoiceId2);
        $this->assertSame(3000000.0, (float) Invoice::findOrFail($invoiceId1)->total_amount);
        $this->assertSame(2000000.0, (float) Invoice::findOrFail($invoiceId2)->total_amount);

        // Mỗi phiếu chỉ lập được 1 lần.
        $this->expectException(DomainException::class);
        app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt1->fresh());
    }

    public function test_phieu_nhan_chua_xac_nhan_khong_lap_duoc_hoa_don(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $receipt = app(GoodsReceiptService::class)->create([
            'purchase_order_id' => null,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'qty' => 5,
                'unit_cost' => 50000,
            ]],
        ], $this->company->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('chưa được xác nhận');
        app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt);
    }

    public function test_hoa_don_mua_nhap_tay_van_ghi_156_khi_ghi_so(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        // Hóa đơn mua nhập tay, không gắn phiếu nhận.
        $invoice = app(InvoiceService::class)->create([
            'type' => 'purchase',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Hàng mua nhập tay',
                'qty' => 4,
                'unit_price' => 100000,
                'tax_rate' => 10,
            ]],
        ], $this->company->id);

        app(InvoiceService::class)->post($invoice->fresh());

        // Nợ 156 (400.000) + Nợ 133 (40.000) / Có 331 (440.000); 335 không đổi.
        $this->assertSame(400000.0, $this->accountTotals('156')['debit']);
        $this->assertSame(40000.0, $this->accountTotals('133')['debit']);
        $this->assertSame(440000.0, $this->accountTotals('331')['credit']);
        $this->assertSame(0.0, $this->accountTotals('335')['credit']);
        $this->assertSame(0.0, $this->accountTotals('335')['debit']);
    }

    public function test_route_lap_hoa_don_mua_tu_phieu_nhan(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        // Trang chi tiết phiếu nhận mở được, kèm dòng đơn mua liên kết.
        $this->actingAs($this->admin)
            ->get('/procurement/goods-receipts/'.$receipt->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('receipt.lines', 1)
                ->where('receipt.lines.0.purchase_order_line.qty', fn ($v) => (float) $v === 10.0));

        // Lập hóa đơn qua route → chuyển tới trang chi tiết hóa đơn.
        $response = $this->actingAs($this->admin)
            ->post('/procurement/goods-receipts/'.$receipt->id.'/create-invoice');

        $invoice = Invoice::where('source_type', GoodsReceipt::class)
            ->where('source_id', $receipt->id)
            ->firstOrFail();

        $response->assertRedirect(route('accounting.invoices.show', $invoice->id));
        $this->assertSame(500000.0, (float) $invoice->total_amount);

        // Lần 2 qua route → quay lại kèm lỗi, không tạo thêm hóa đơn.
        $this->actingAs($this->admin)
            ->from('/procurement/goods-receipts/'.$receipt->id)
            ->post('/procurement/goods-receipts/'.$receipt->id.'/create-invoice')
            ->assertRedirect('/procurement/goods-receipts/'.$receipt->id)
            ->assertSessionHas('error');

        $this->assertSame(1, Invoice::where('source_type', GoodsReceipt::class)
            ->where('source_id', $receipt->id)->count());
    }

    public function test_user_khong_co_quyen_lap_hoa_don_bi_chan_route(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        $kho = $this->createUserWithRole('kho-invoice@erp.local', 'kho');

        $this->actingAs($kho)
            ->post('/procurement/goods-receipts/'.$receipt->id.'/create-invoice')
            ->assertForbidden();

        $this->assertSame(0, Invoice::where('source_type', GoodsReceipt::class)
            ->where('source_id', $receipt->id)->count());
    }
}
