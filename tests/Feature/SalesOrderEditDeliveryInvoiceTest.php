<?php

namespace Tests\Feature;

use App\Modules\Accounting\Events\InvoicePosted;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Reporting\Jobs\UpdateDailySummaryJob;
use App\Modules\Reporting\Listeners\ClearReportCacheOnInvoicePosted;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\DeliveryOrder;
use App\Modules\Sales\Models\DeliveryOrderLine;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationLine;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\DeliveryOrderService;
use App\Modules\Sales\Services\QuotationService;
use App\Modules\Sales\Services\SalesOrderService;
use App\Core\Contracts\StockServiceInterface;
use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Sửa đơn bán/báo giá nháp (5a), giao nốt phần còn lại (5b),
 * lập hóa đơn từ phần đã giao (5c).
 */
class SalesOrderEditDeliveryInvoiceTest extends TestCase
{
    use CreatesErpFixtures {
        seedBaseData as traitSeedBaseData;
    }

    use RefreshDatabase;

    /**
     * Seed nền + sửa quirk của fixture: ở test đầu tiên của tiến trình,
     * CompanySeeder chạy khi CurrentCompany chưa được set nên đơn vị tính
     * được seed với company_id null rồi bị CompanyScope ẩn đi, khiến
     * createProduct() không tìm thấy unit 'cai'. Gắn các unit dùng chung
     * về công ty kiểm thử để test chạy được cả khi chạy riêng file.
     */
    protected function seedBaseData(): void
    {
        $this->traitSeedBaseData();

        Unit::withoutGlobalScope(CompanyScope::class)
            ->whereNull('company_id')
            ->update(['company_id' => $this->company->id]);
    }

    protected function createCustomer(): Customer
    {
        return Customer::create([
            'company_id' => $this->company->id,
            'code' => 'KH-'.uniqid(),
            'name' => 'Khách kiểm thử',
            'credit_limit' => 0,
            'payment_term_days' => 30,
        ]);
    }

    /** Tạo đơn bán trực tiếp qua model (kèm dòng) để chủ động trạng thái/số lượng đã giao. */
    protected function createOrder(array $orderAttrs, array $linesAttrs): SalesOrder
    {
        $order = SalesOrder::create(array_merge([
            'company_id' => $this->company->id,
            'code' => 'SO-'.uniqid(),
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ], $orderAttrs));

        foreach ($linesAttrs as $lineAttrs) {
            SalesOrderLine::create(array_merge([
                'company_id' => $this->company->id,
                'sales_order_id' => $order->id,
                'delivered_qty' => 0,
                'invoiced_qty' => 0,
                'discount_rate' => 0,
                'tax_rate' => 0,
            ], $lineAttrs));
        }

        return $order->fresh(['lines']);
    }

    // ---------- 5a: sửa đơn nháp ----------

    public function test_sửa_đơn_nháp_thêm_xóa_sửa_dòng_và_tính_lại_tổng(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $productA = $this->createProduct('SP-A');
        $productB = $this->createProduct('SP-B');
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id],
            [
                ['product_id' => $productA->id, 'qty' => 5, 'unit_price' => 100000, 'tax_rate' => 10, 'line_total' => 550000],
                ['product_id' => $productB->id, 'qty' => 1, 'unit_price' => 50000, 'line_total' => 50000],
            ]
        );
        [$lineA, $lineB] = $order->lines->all();

        $updated = app(SalesOrderService::class)->update($order, [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'discount_amount' => 0,
            'note' => 'Đã sửa',
            'lines' => [
                // Sửa dòng A: tăng số lượng 5 → 10.
                ['id' => $lineA->id, 'product_id' => $productA->id, 'qty' => 10, 'unit_price' => 100000, 'discount_rate' => 0, 'tax_rate' => 10],
                // Dòng B vắng mặt → bị xóa. Thêm dòng mới (không id).
                ['product_id' => $productB->id, 'qty' => 2, 'unit_price' => 50000, 'discount_rate' => 10, 'tax_rate' => 0],
            ],
        ]);

        $this->assertSame(2, $updated->lines->count());
        $this->assertNull(SalesOrderLine::find($lineB->id));
        // Dòng A: 10 × 100.000 = 1.000.000 + thuế 10% = 1.100.000.
        // Dòng mới: 2 × 50.000 × (1 − 10%) = 90.000, thuế 0.
        $this->assertSame(1090000.0, (float) $updated->subtotal);
        $this->assertSame(100000.0, (float) $updated->tax_amount);
        $this->assertSame(1190000.0, (float) $updated->total_amount);
        $this->assertSame('Đã sửa', $updated->note);
    }

    public function test_sửa_đơn_đã_xác_nhận_bị_chặn(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'confirmed'],
            [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 100000, 'line_total' => 100000]]
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('nháp');

        app(SalesOrderService::class)->update($order, [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'lines' => [
                ['id' => $order->lines->first()->id, 'product_id' => $product->id, 'qty' => 2, 'unit_price' => 100000],
            ],
        ]);
    }

    public function test_sửa_đơn_nháp_qua_http_và_đơn_đã_xác_nhận_bị_chặn(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();

        $draft = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id],
            [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 100000, 'line_total' => 100000]]
        );

        $this->actingAs($this->admin)
            ->put('/sales/orders/'.$draft->id, [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'discount_amount' => 0,
                'lines' => [
                    ['id' => $draft->lines->first()->id, 'product_id' => $product->id, 'qty' => 3, 'unit_price' => 100000, 'discount_rate' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertRedirect(route('sales.orders.show', $draft->id));

        $this->assertSame(300000.0, (float) $draft->fresh()->total_amount);

        $confirmed = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'confirmed'],
            [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 100000, 'line_total' => 100000]]
        );

        $this->actingAs($this->admin)
            ->from('/sales/orders/'.$confirmed->id)
            ->put('/sales/orders/'.$confirmed->id, [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'lines' => [
                    ['id' => $confirmed->lines->first()->id, 'product_id' => $product->id, 'qty' => 3, 'unit_price' => 100000],
                ],
            ])
            ->assertRedirect('/sales/orders/'.$confirmed->id)
            ->assertSessionHas('error');

        $this->assertSame(1.0, (float) $confirmed->fresh()->lines->first()->qty);
    }

    public function test_sửa_báo_giá_nháp_ok_và_báo_giá_đã_gửi_bị_chặn(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $customer = $this->createCustomer();

        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'code' => 'QT-'.uniqid(),
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100000,
        ]);
        $line = QuotationLine::create([
            'company_id' => $this->company->id,
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => 100000,
            'discount_rate' => 0,
            'tax_rate' => 0,
            'line_total' => 100000,
        ]);

        $updated = app(QuotationService::class)->update($quotation, [
            'customer_id' => $customer->id,
            'discount_amount' => 0,
            'lines' => [
                ['id' => $line->id, 'product_id' => $product->id, 'qty' => 2, 'unit_price' => 100000, 'discount_rate' => 0, 'tax_rate' => 0],
            ],
        ]);

        $this->assertSame(200000.0, (float) $updated->total_amount);

        $quotation->update(['status' => 'sent']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('nháp');

        app(QuotationService::class)->update($quotation->fresh(), [
            'customer_id' => $customer->id,
            'lines' => [
                ['id' => $line->id, 'product_id' => $product->id, 'qty' => 3, 'unit_price' => 100000],
            ],
        ]);
    }

    // ---------- 5b: giao nốt ----------

    protected function stockIn(int $productId, int $warehouseId, float $qty): void
    {
        app(StockServiceInterface::class)->receive($productId, $warehouseId, null, $qty, 50000, self::class, 1);
    }

    public function test_giao_nốt_đủ_tồn_tạo_phiếu_đúng_số_thiếu(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockIn($product->id, $warehouse->id, 10);

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'partially_delivered'],
            [['product_id' => $product->id, 'qty' => 10, 'delivered_qty' => 4, 'unit_price' => 100000, 'line_total' => 1000000]]
        );

        $result = app(DeliveryOrderService::class)->createForRemaining($order->fresh());

        $delivery = $result['order'];
        $this->assertSame('draft', $delivery->status);
        $this->assertSame($order->id, $delivery->sales_order_id);
        $this->assertCount(1, $delivery->lines);
        // Còn thiếu 10 − 4 = 6.
        $this->assertSame(6.0, (float) $delivery->lines->first()->qty);
        $this->assertSame($order->lines->first()->id, $delivery->lines->first()->sales_order_line_id);
    }

    public function test_giao_nốt_thiếu_tồn_chỉ_giao_phần_đủ_tồn(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockIn($product->id, $warehouse->id, 3);

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'partially_delivered'],
            [['product_id' => $product->id, 'qty' => 10, 'delivered_qty' => 4, 'unit_price' => 100000, 'line_total' => 1000000]]
        );

        $result = app(DeliveryOrderService::class)->createForRemaining($order->fresh());

        // Thiếu 6 nhưng chỉ còn 3 tồn → giao 3, kèm cảnh báo.
        $this->assertSame(3.0, (float) $result['order']->lines->first()->qty);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_giao_nốt_khi_đã_có_phiếu_nháp_bao_hết_thì_không_tạo_thêm(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockIn($product->id, $warehouse->id, 10);

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'partially_delivered'],
            [['product_id' => $product->id, 'qty' => 10, 'delivered_qty' => 4, 'unit_price' => 100000, 'line_total' => 1000000]]
        );
        $line = $order->lines->first();

        // Phiếu nháp đã bao hết phần thiếu (6).
        $draft = DeliveryOrder::create([
            'company_id' => $this->company->id,
            'code' => 'DO-'.uniqid(),
            'sales_order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'delivery_date' => now()->toDateString(),
        ]);
        DeliveryOrderLine::create([
            'company_id' => $this->company->id,
            'delivery_order_id' => $draft->id,
            'sales_order_line_id' => $line->id,
            'product_id' => $product->id,
            'qty' => 6,
            'unit_cost' => 0,
        ]);

        try {
            app(DeliveryOrderService::class)->createForRemaining($order->fresh());
            $this->fail('Phần thiếu đã nằm trong phiếu nháp thì không được tạo phiếu mới.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('phiếu giao nháp', $e->getMessage());
        }

        $this->assertSame(1, DeliveryOrder::where('sales_order_id', $order->id)->count());
    }

    public function test_giao_nốt_khi_hết_tồn_bị_chặn(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        // Không nhập tồn.

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'confirmed'],
            [['product_id' => $product->id, 'qty' => 5, 'delivered_qty' => 0, 'unit_price' => 100000, 'line_total' => 500000]]
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tồn khả dụng');

        app(DeliveryOrderService::class)->createForRemaining($order->fresh());
    }

    public function test_giao_nốt_qua_http_chuyển_tới_phiếu_giao_mới(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockIn($product->id, $warehouse->id, 10);

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'confirmed'],
            [['product_id' => $product->id, 'qty' => 5, 'delivered_qty' => 2, 'unit_price' => 100000, 'line_total' => 500000]]
        );

        $this->actingAs($this->admin)
            ->post('/sales/orders/'.$order->id.'/deliver-remaining')
            ->assertRedirectContains('/sales/deliveries/');

        $delivery = DeliveryOrder::where('sales_order_id', $order->id)->firstOrFail();
        $this->assertSame(3.0, (float) $delivery->lines->first()->qty);
    }

    // ---------- 5c: lập hóa đơn từ đơn ----------

    public function test_lập_hóa_đơn_từ_phần_đã_giao_đúng_số_tiền_và_cập_nhật_invoiced_qty(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $productA = $this->createProduct('SP-A');
        $productB = $this->createProduct('SP-B');
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'partially_delivered'],
            [
                ['product_id' => $productA->id, 'qty' => 10, 'delivered_qty' => 6, 'unit_price' => 100000, 'tax_rate' => 10, 'line_total' => 1100000],
                ['product_id' => $productB->id, 'qty' => 5, 'delivered_qty' => 5, 'unit_price' => 20000, 'discount_rate' => 10, 'tax_rate' => 0, 'line_total' => 90000],
            ]
        );

        $invoiceId = app(SalesOrderService::class)->createInvoiceFromDelivered($order->fresh());

        $invoice = Invoice::with('lines')->findOrFail($invoiceId);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('sale', $invoice->type);
        $this->assertSame(SalesOrder::class, $invoice->source_type);
        $this->assertSame($order->id, $invoice->source_id);
        $this->assertSame($customer->id, $invoice->customer_id);
        // Dòng A: 6 × 100.000 = 600.000 + thuế 10% = 60.000.
        // Dòng B: 5 × (20.000 × 90%) = 90.000, thuế 0.
        $this->assertSame(690000.0, (float) $invoice->subtotal);
        $this->assertSame(60000.0, (float) $invoice->tax_amount);
        $this->assertSame(750000.0, (float) $invoice->total_amount);
        $this->assertSame(now()->addDays(30)->toDateString(), $invoice->due_date->toDateString());

        [$lineA, $lineB] = $order->fresh()->lines->all();
        $this->assertSame(6.0, (float) $lineA->invoiced_qty);
        $this->assertSame(5.0, (float) $lineB->invoiced_qty);

        // Lập lần 2 khi hết phần chưa lập thì bị chặn (chống lập trùng).
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('không còn phần đã giao');

        app(SalesOrderService::class)->createInvoiceFromDelivered($order->fresh());
    }

    public function test_lập_hóa_đơn_từng_phần_cộng_dồn_invoiced_qty(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'partially_delivered'],
            [['product_id' => $product->id, 'qty' => 10, 'delivered_qty' => 4, 'unit_price' => 100000, 'line_total' => 1000000]]
        );

        $firstId = app(SalesOrderService::class)->createInvoiceFromDelivered($order->fresh());
        $this->assertSame(400000.0, (float) Invoice::findOrFail($firstId)->total_amount);

        // Giao thêm 3 rồi lập hóa đơn lần 2 — chỉ lập phần mới giao.
        $order->lines->first()->update(['delivered_qty' => 7]);

        $secondId = app(SalesOrderService::class)->createInvoiceFromDelivered($order->fresh());
        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(300000.0, (float) Invoice::findOrFail($secondId)->total_amount);
        $this->assertSame(7.0, (float) $order->fresh()->lines->first()->invoiced_qty);
    }

    public function test_lập_hóa_đơn_trừ_chiết_khấu_cấp_đơn_theo_tỷ_lệ_phần_đã_giao(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $productA = $this->createProduct('SP-A');
        $productB = $this->createProduct('SP-B');
        $customer = $this->createCustomer();

        // Đơn: 2 dòng, mỗi dòng 10 × 100.000 = 1.000.000; subtotal 2.000.000,
        // chiết khấu cấp đơn 200.000 (10%), thuế 10%.
        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'partially_delivered',
             'subtotal' => 2000000, 'discount_amount' => 200000, 'tax_amount' => 200000, 'total_amount' => 2000000],
            [
                ['product_id' => $productA->id, 'qty' => 10, 'delivered_qty' => 10, 'unit_price' => 100000, 'tax_rate' => 10, 'line_total' => 1100000],
                ['product_id' => $productB->id, 'qty' => 10, 'delivered_qty' => 5, 'unit_price' => 100000, 'tax_rate' => 10, 'line_total' => 1100000],
            ]
        );

        $firstId = app(SalesOrderService::class)->createInvoiceFromDelivered($order->fresh());
        $first = Invoice::findOrFail($firstId);
        // Phần đã giao: 1.000.000 + 500.000 = 1.500.000; trừ 10% chiết khấu đơn
        // → 1.350.000; thuế 10% tính trên giá đã trừ chiết khấu = 135.000.
        $this->assertSame(1350000.0, (float) $first->subtotal);
        $this->assertSame(135000.0, (float) $first->tax_amount);
        $this->assertSame(1485000.0, (float) $first->total_amount);

        // Giao nốt 5 còn lại rồi lập hóa đơn lần 2 — phần chiết khấu còn lại
        // (200.000 − 150.000 = 50.000) trừ vào hóa đơn này.
        $order->lines->last()->update(['delivered_qty' => 10]);

        $secondId = app(SalesOrderService::class)->createInvoiceFromDelivered($order->fresh());
        $second = Invoice::findOrFail($secondId);
        $this->assertSame(450000.0, (float) $second->subtotal);
        $this->assertSame(45000.0, (float) $second->tax_amount);
        $this->assertSame(495000.0, (float) $second->total_amount);

        // Cộng 2 hóa đơn = đúng giá trị đơn sau chiết khấu: 1.800.000
        // tiền hàng + 180.000 thuế GTGT tính trên giá đã trừ chiết khấu.
        $this->assertSame(1800000.0, (float) $first->subtotal + (float) $second->subtotal);
        $this->assertSame(180000.0, (float) $first->tax_amount + (float) $second->tax_amount);
    }

    public function test_lập_hóa_đơn_khi_đơn_chưa_giao_bị_chặn(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'confirmed'],
            [['product_id' => $product->id, 'qty' => 5, 'delivered_qty' => 0, 'unit_price' => 100000, 'line_total' => 500000]]
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('chưa giao hàng');

        app(SalesOrderService::class)->createInvoiceFromDelivered($order->fresh());
    }

    public function test_lập_hóa_đơn_qua_http_chuyển_tới_trang_hóa_đơn(): void
    {
        $this->seedBaseData();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'status' => 'delivered'],
            [['product_id' => $product->id, 'qty' => 2, 'delivered_qty' => 2, 'unit_price' => 100000, 'line_total' => 200000]]
        );

        $this->actingAs($this->admin)
            ->post('/sales/orders/'.$order->id.'/create-invoice')
            ->assertRedirectContains('/accounting/invoices/');

        $invoice = Invoice::where('source_type', SalesOrder::class)->where('source_id', $order->id)->firstOrFail();
        $this->assertSame(200000.0, (float) $invoice->total_amount);
    }

    // ---------- Sửa listener báo cáo: tổng hợp theo invoice_date ----------

    public function test_tổng_hợp_doanh_thu_theo_ngày_hóa_đơn_không_phải_hôm_nay(): void
    {
        $this->seedBaseData();
        $customer = $this->createCustomer();
        $invoiceDate = now()->subDays(10)->toDateString();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'code' => 'INV-'.uniqid(),
            'type' => 'sale',
            'customer_id' => $customer->id,
            'invoice_date' => $invoiceDate,
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total_amount' => 100000,
            'status' => 'posted',
        ]);

        Queue::fake();

        (new ClearReportCacheOnInvoicePosted)->handle(new InvoicePosted(
            $invoice->id,
            $this->company->id,
            'sale',
            null,
            null,
            $customer->id,
            null,
            100000,
        ));

        Queue::assertPushed(UpdateDailySummaryJob::class, function (UpdateDailySummaryJob $job) use ($invoiceDate) {
            return $job->companyId === $this->company->id && $job->date === $invoiceDate;
        });
    }
}
