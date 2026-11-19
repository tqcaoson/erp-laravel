<?php

namespace Tests\Feature;

use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\InvoiceService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Procurement\Services\GoodsReceiptService;
use App\Modules\Reporting\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\Feature\Concerns\CreatesProcurementFixtures;
use Tests\TestCase;

/**
 * Báo cáo công nợ phải trả từ sổ cái 331 + KPI phải trả + cảnh báo
 * phiếu nhận chờ xác nhận trên dashboard.
 *
 * Luồng hạch toán (TK 335): phiếu nhận xác nhận ghi Nợ 156 / Có 335;
 * hóa đơn mua từ phiếu nhận khi ghi sổ kết chuyển Nợ 335 / Có 331.
 */
class PayablesReportTest extends TestCase
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

    public function test_phieu_nhan_da_xac_nhan_ghi_335_chua_len_331(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        // Nợ 156 / Có 335 đúng 500.000; 331 chưa phát sinh (chưa có hóa đơn).
        $this->assertSame(500000.0, $this->accountTotals('156')['debit']);
        $this->assertSame(500000.0, $this->accountTotals('335')['credit']);
        $this->assertSame(0.0, $this->accountTotals('331')['credit']);

        // Báo cáo phải trả (đọc 331) chưa có dòng nào.
        $this->assertSame([], app(ReportService::class)->payablesBySupplier($this->company->id));
    }

    public function test_hoa_don_mua_tu_phieu_nhan_ket_chuyen_335_sang_331_khong_ghi_trung_156(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        $invoiceId = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());
        app(InvoiceService::class)->post(Invoice::findOrFail($invoiceId));

        // 156 chỉ tăng 1 lần (lúc xác nhận phiếu nhận), không ghi trùng khi ghi sổ hóa đơn.
        $this->assertSame(500000.0, $this->accountTotals('156')['debit']);
        // 335 về 0 sau khi ghi sổ hóa đơn (Có 500.000 − Nợ 500.000).
        $totals335 = $this->accountTotals('335');
        $this->assertSame(500000.0, $totals335['credit']);
        $this->assertSame(500000.0, $totals335['debit']);
        // 331 = tổng hóa đơn.
        $this->assertSame(500000.0, $this->accountTotals('331')['credit']);

        // Báo cáo phải trả: NCC đúng số dư, nhóm tuổi nợ 0–30 ngày.
        $rows = app(ReportService::class)->payablesBySupplier($this->company->id);
        $this->assertCount(1, $rows);
        $this->assertSame($supplier->id, $rows[0]['supplier_id']);
        $this->assertSame('NCC-TEST', $rows[0]['supplier_code']);
        $this->assertSame(500000.0, $rows[0]['balance']);
        $this->assertSame(500000.0, $rows[0]['buckets']['0-30']);
        $this->assertSame(0.0, $rows[0]['buckets']['90+']);
    }

    public function test_thanh_toan_lam_giam_cong_no_phai_tra(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        $invoiceId = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());
        $invoice = Invoice::findOrFail($invoiceId);
        app(InvoiceService::class)->post($invoice);

        // Trả 200.000 (bút toán Nợ 331 / Có 112).
        app(InvoiceService::class)->recordPayment($invoice->fresh(), [
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
        ]);

        $totals331 = $this->accountTotals('331');
        $this->assertSame(500000.0, $totals331['credit']);
        $this->assertSame(200000.0, $totals331['debit']);

        $rows = app(ReportService::class)->payablesBySupplier($this->company->id);
        $this->assertCount(1, $rows);
        $this->assertSame(300000.0, $rows[0]['balance']);
    }

    public function test_bao_cao_phan_nhom_tuoi_no_theo_ngay_phat_sinh_but_toan(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();

        // Bút toán cũ 40 ngày trước → nhóm 31–60; bút toán 100 ngày trước → nhóm 90+.
        app(JournalEntryService::class)->post([
            'entry_date' => now()->subDays(40)->toDateString(),
            'description' => 'Mua hàng cũ 40 ngày',
            'lines' => [
                ['account_code' => '156', 'debit' => 100000, 'credit' => 0],
                ['account_code' => '331', 'debit' => 0, 'credit' => 100000, 'partner_type' => 'supplier', 'partner_id' => $supplier->id],
            ],
        ]);
        app(JournalEntryService::class)->post([
            'entry_date' => now()->subDays(100)->toDateString(),
            'description' => 'Mua hàng cũ 100 ngày',
            'lines' => [
                ['account_code' => '156', 'debit' => 70000, 'credit' => 0],
                ['account_code' => '331', 'debit' => 0, 'credit' => 70000, 'partner_type' => 'supplier', 'partner_id' => $supplier->id],
            ],
        ]);

        $rows = app(ReportService::class)->payablesBySupplier($this->company->id);
        $this->assertCount(1, $rows);
        $this->assertSame(170000.0, $rows[0]['balance']);
        $this->assertSame(0.0, $rows[0]['buckets']['0-30']);
        $this->assertSame(100000.0, $rows[0]['buckets']['31-60']);
        $this->assertSame(0.0, $rows[0]['buckets']['61-90']);
        $this->assertSame(70000.0, $rows[0]['buckets']['90+']);
    }

    public function test_trang_bao_cao_phai_tra_va_xuat_csv(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);
        $invoiceId = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());
        app(InvoiceService::class)->post(Invoice::findOrFail($invoiceId));

        $this->actingAs($this->admin)
            ->get('/reports/payables-by-supplier')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.supplier_id', $supplier->id)
                ->where('totalBalance', fn ($v) => (float) $v === 500000.0));

        $this->actingAs($this->admin)
            ->get('/reports/payables-by-supplier?export=csv')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Trang liệt kê báo cáo có mục công nợ phải trả.
        $this->actingAs($this->admin)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reports', 4));
    }

    public function test_dashboard_hien_kpi_phai_tra_va_canh_bao_phieu_nhan_cho_xac_nhan(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        // 1 phiếu nhận đã xác nhận + hóa đơn đã ghi sổ → KPI phải trả 500.000.
        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);
        $invoiceId = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());
        app(InvoiceService::class)->post(Invoice::findOrFail($invoiceId));

        // 1 phiếu nhận nháp (chưa xác nhận) → cảnh báo dashboard.
        app(GoodsReceiptService::class)->create([
            'purchase_order_id' => null,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'qty' => 2,
                'unit_cost' => 10000,
            ]],
        ], $this->company->id);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpi.payables', fn ($v) => (float) $v === 500000.0)
                ->has('alerts.pending_goods_receipts', 1)
                ->where('alerts.pending_goods_receipts_total', 1));
    }

    public function test_bao_cao_hien_hang_ve_chua_co_hoa_don_335(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);

        // 331 vẫn trống (chưa có hóa đơn), nhưng mục 335 hiện 500.000.
        $this->assertSame([], app(ReportService::class)->payablesBySupplier($this->company->id));
        $pending = app(ReportService::class)->goodsReceivedNotInvoiced($this->company->id);
        $this->assertCount(1, $pending);
        $this->assertSame($supplier->id, $pending[0]['supplier_id']);
        $this->assertSame(500000.0, $pending[0]['balance']);

        $this->actingAs($this->admin)
            ->get('/reports/payables-by-supplier')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 0)
                ->has('pendingRows', 1)
                ->where('pendingTotal', fn ($v) => (float) $v === 500000.0));
    }

    public function test_kpi_phai_tra_tru_tien_da_thanh_toan(): void
    {
        $this->seedBaseData();
        $supplier = $this->createSupplier();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $order = $this->createApprovedPurchaseOrder($supplier, $product, 10, 50000);
        $receipt = $this->createValidatedGoodsReceipt($order, $supplier, $warehouse, $product, 10, 50000);
        $invoiceId = app(GoodsReceiptService::class)->createInvoiceFromReceipt($receipt->fresh());
        $invoice = Invoice::findOrFail($invoiceId);
        app(InvoiceService::class)->post($invoice->fresh());
        app(InvoiceService::class)->recordPayment($invoice->fresh(), [
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
        ]);

        // KPI đọc từ sổ cái 331 nên còn 300.000 (cách cộng tổng hóa đơn cũ
        // sẽ vẫn hiện 500.000 vì không trừ tiền đã trả).
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpi.payables', fn ($v) => (float) $v === 300000.0));

        // 335 đã kết chuyển hết, 331 còn 300.000.
        $this->assertSame([], app(ReportService::class)->goodsReceivedNotInvoiced($this->company->id));
        $rows = app(ReportService::class)->payablesBySupplier($this->company->id);
        $this->assertCount(1, $rows);
        $this->assertSame(300000.0, $rows[0]['balance']);
    }
}
