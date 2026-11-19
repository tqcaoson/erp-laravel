<?php

namespace Tests\Feature;

use App\Core\Models\ApprovalLog;
use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Policies\SalesOrderPolicy;
use App\Modules\Sales\Services\SalesOrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Maker-checker khi duyệt đơn bán vượt hạn mức: người tạo đơn không được
 * duyệt đơn của chính mình; người khác duyệt được và có ApprovalLog.
 */
class MakerCheckerTest extends TestCase
{
    use CreatesErpFixtures {
        seedBaseData as traitSeedBaseData;
    }

    use RefreshDatabase;

    protected function seedBaseData(): void
    {
        $this->traitSeedBaseData();

        Unit::withoutGlobalScope(CompanyScope::class)
            ->whereNull('company_id')
            ->update(['company_id' => $this->company->id]);
    }

    protected function createPendingOrder(?int $createdBy = null): SalesOrder
    {
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'code' => 'KH-'.uniqid(),
            'name' => 'Khách kiểm thử maker-checker',
            'credit_limit' => 0,
            'payment_term_days' => 30,
        ]);

        $order = SalesOrder::create([
            'company_id' => $this->company->id,
            'code' => 'SO-'.uniqid(),
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'pending_approval',
            'order_date' => now()->toDateString(),
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100000,
        ]);

        // created_by không nằm trong $fillable nên gán trực tiếp sau khi tạo.
        if ($createdBy !== null) {
            $order->forceFill(['created_by' => $createdBy])->save();
        }

        SalesOrderLine::create([
            'company_id' => $this->company->id,
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => 100000,
            'discount_rate' => 0,
            'tax_rate' => 0,
            'line_total' => 100000,
        ]);

        return $order;
    }

    public function test_nguoi_tao_don_khong_duoc_duyet_don_cua_minh(): void
    {
        $this->seedBaseData();
        $creator = $this->createUserWithRole('nguoi-tao@erp.local', 'giam_doc');
        $order = $this->createPendingOrder($creator->id);

        $this->actingAs($creator);

        try {
            app(SalesOrderService::class)->approve($order->fresh());
            $this->fail('Người tạo đơn phải bị chặn khi duyệt đơn của chính mình.');
        } catch (DomainException $e) {
            $this->assertSame('Người tạo đơn không được duyệt đơn của chính mình.', $e->getMessage());
        }

        $this->assertSame('pending_approval', $order->fresh()->status);
        $this->assertSame(0, ApprovalLog::where('approvable_type', SalesOrder::class)
            ->where('approvable_id', $order->id)->count());
    }

    public function test_nguoi_khac_duyet_duoc_va_ghi_approval_log(): void
    {
        $this->seedBaseData();
        $creator = $this->createUserWithRole('nguoi-tao-2@erp.local', 'giam_doc');
        $approver = $this->createUserWithRole('nguoi-duyet@erp.local', 'giam_doc');
        $order = $this->createPendingOrder($creator->id);

        $this->actingAs($approver);
        $result = app(SalesOrderService::class)->approve($order->fresh());

        $this->assertSame('confirmed', $result['status']);
        $this->assertSame('confirmed', $order->fresh()->status);

        $log = ApprovalLog::where('approvable_type', SalesOrder::class)
            ->where('approvable_id', $order->id)
            ->firstOrFail();
        $this->assertSame($approver->id, $log->approver_id);
        $this->assertSame('approved', $log->decision);
        $this->assertSame(1, $log->level);
    }

    public function test_duyet_khong_dang_nhap_van_chay_duoc_khong_vo_test_cu(): void
    {
        $this->seedBaseData();
        // Đơn không có người tạo + gọi trực tiếp không đăng nhập:
        // kiểm tra maker-checker bỏ qua, không ghi log (approver_id bắt buộc).
        $order = $this->createPendingOrder(null);

        $result = app(SalesOrderService::class)->approve($order->fresh());

        $this->assertSame('confirmed', $result['status']);
        $this->assertSame(0, ApprovalLog::where('approvable_type', SalesOrder::class)
            ->where('approvable_id', $order->id)->count());
    }

    public function test_policy_duyet_chan_nguoi_tao_cho_phep_nguoi_khac(): void
    {
        $this->seedBaseData();
        $creator = $this->createUserWithRole('nguoi-tao-3@erp.local', 'giam_doc');
        $approver = $this->createUserWithRole('nguoi-duyet-3@erp.local', 'giam_doc');
        $noPermission = $this->createUserWithRole('khong-quyen@erp.local', 'ban_hang');
        $order = $this->createPendingOrder($creator->id);

        $policy = new SalesOrderPolicy;

        $this->assertFalse($policy->approve($creator, $order));
        $this->assertTrue($policy->approve($approver, $order));
        $this->assertFalse($policy->approve($noPermission, $order));
    }

    public function test_route_duyet_bi_chan_voi_nguoi_tao_duoc_voi_nguoi_khac(): void
    {
        $this->seedBaseData();
        $creator = $this->createUserWithRole('nguoi-tao-4@erp.local', 'giam_doc');
        $approver = $this->createUserWithRole('nguoi-duyet-4@erp.local', 'giam_doc');
        $order = $this->createPendingOrder($creator->id);

        // Người tạo duyệt qua route → quay lại kèm lỗi, đơn vẫn chờ duyệt.
        $this->actingAs($creator)
            ->from('/sales/orders/'.$order->id)
            ->post('/sales/orders/'.$order->id.'/approve')
            ->assertRedirect('/sales/orders/'.$order->id)
            ->assertSessionHas('error', 'Người tạo đơn không được duyệt đơn của chính mình.');
        $this->assertSame('pending_approval', $order->fresh()->status);

        // Người khác duyệt qua route → thành công.
        $this->actingAs($approver)
            ->from('/sales/orders/'.$order->id)
            ->post('/sales/orders/'.$order->id.'/approve')
            ->assertRedirect('/sales/orders/'.$order->id)
            ->assertSessionHas('success');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(1, ApprovalLog::where('approvable_type', SalesOrder::class)
            ->where('approvable_id', $order->id)->count());
    }
}
