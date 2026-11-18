<?php

namespace Tests\Feature;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Đơn bán vượt hạn mức công nợ KHÔNG bị từ chối hẳn mà chuyển sang
 * trạng thái chờ duyệt (đúng ví dụ mục 8 của thiết kế).
 */
class SalesOrderCreditLimitTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    public function test_đơn_bán_vượt_hạn_mức_chuyển_chờ_duyệt(): void
    {
        $this->seedBaseData();

        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'code' => 'KH-TEST',
            'name' => 'Khách kiểm thử hạn mức',
            'credit_limit' => 50000000,
            'payment_term_days' => 30,
        ]);

        $order = SalesOrder::create([
            'company_id' => $this->company->id,
            'code' => 'SO-TEST-001',
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

        $result = app(SalesOrderService::class)->confirm($order->fresh());

        $this->assertSame('pending_approval', $order->fresh()->status);
        $this->assertTrue($result['needs_approval'] ?? false);
    }

    public function test_đơn_bán_trong_hạn_mức_được_xác_nhận_ngay(): void
    {
        $this->seedBaseData();

        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'code' => 'KH-TEST-2',
            'name' => 'Khách kiểm thử trong hạn mức',
            'credit_limit' => 50000000,
            'payment_term_days' => 30,
        ]);

        $order = SalesOrder::create([
            'company_id' => $this->company->id,
            'code' => 'SO-TEST-002',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 10000000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 10000000,
        ]);

        SalesOrderLine::create([
            'company_id' => $this->company->id,
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 100,
            'unit_price' => 100000,
            'discount_rate' => 0,
            'tax_rate' => 0,
            'line_total' => 10000000,
        ]);

        app(SalesOrderService::class)->confirm($order->fresh());

        $this->assertSame('confirmed', $order->fresh()->status);
    }
}
