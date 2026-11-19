<?php

namespace Tests\Feature;

use App\Core\Models\Unit;
use App\Core\Support\CompanyScope;
use App\Models\User;
use App\Modules\Inventory\Models\StockMoveRequest as StockMoveRequestModel;
use App\Modules\Inventory\Models\StockQuant;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Luồng duyệt phiếu kho tay (20/11/2026): tạo phiếu không đổi tồn,
 * duyệt xong tồn mới đổi, từ chối thì tồn giữ nguyên.
 */
class StockMoveApprovalTest extends TestCase
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

    private function stockQty(int $productId, int $warehouseId): float
    {
        return (float) StockQuant::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('qty');
    }

    public function test_tao_phieu_kho_tay_khong_doi_ton_va_co_phieu_pending(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct('SP-MOVE-1');

        $this->actingAs($this->user('kho@erp.local'))->post('/inventory/stock/move', [
            'type' => 'receive',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 10,
            'unit_cost' => 1000,
        ])->assertRedirect();

        $this->assertSame(0.0, $this->stockQty($product->id, $warehouse->id));

        $request = StockMoveRequestModel::query()->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame('receive', $request->type);
        $this->assertSame(10.0, (float) $request->qty);
        $this->assertSame($this->user('kho@erp.local')->id, $request->requested_by);
    }

    public function test_duyet_phieu_nhap_thi_ton_tang_dung_so(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct('SP-MOVE-2');
        $kho = $this->user('kho@erp.local');

        $this->actingAs($kho)->post('/inventory/stock/move', [
            'type' => 'receive', 'product_id' => $product->id,
            'warehouse_id' => $warehouse->id, 'qty' => 8, 'unit_cost' => 2000,
        ])->assertRedirect();

        $request = StockMoveRequestModel::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/inventory/stock-requests/'.$request->id.'/approve')
            ->assertRedirect();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($this->admin->id, $request->fresh()->approved_by);
        $this->assertSame(8.0, $this->stockQty($product->id, $warehouse->id));
    }

    public function test_duyet_phieu_xuat_thi_ton_giam_dung_so(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct('SP-MOVE-3');
        app(StockService::class)->receive($product->id, $warehouse->id, null, 20, 1000, 'test', 1);
        $this->assertSame(20.0, $this->stockQty($product->id, $warehouse->id));

        $this->actingAs($this->user('kho@erp.local'))->post('/inventory/stock/move', [
            'type' => 'issue', 'product_id' => $product->id,
            'warehouse_id' => $warehouse->id, 'qty' => 5,
        ])->assertRedirect();

        $request = StockMoveRequestModel::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/inventory/stock-requests/'.$request->id.'/approve')
            ->assertRedirect();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(15.0, $this->stockQty($product->id, $warehouse->id));
    }

    public function test_tu_choi_phieu_thi_ton_khong_doi(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct('SP-MOVE-4');

        $this->actingAs($this->user('kho@erp.local'))->post('/inventory/stock/move', [
            'type' => 'receive', 'product_id' => $product->id,
            'warehouse_id' => $warehouse->id, 'qty' => 12, 'unit_cost' => 1000,
        ])->assertRedirect();

        $request = StockMoveRequestModel::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/inventory/stock-requests/'.$request->id.'/reject')
            ->assertRedirect();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame($this->admin->id, $request->fresh()->approved_by);
        $this->assertSame(0.0, $this->stockQty($product->id, $warehouse->id));
    }

    public function test_kho_khong_duoc_duyet_phieu(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct('SP-MOVE-5');

        $this->actingAs($this->user('kho@erp.local'))->post('/inventory/stock/move', [
            'type' => 'receive', 'product_id' => $product->id,
            'warehouse_id' => $warehouse->id, 'qty' => 4, 'unit_cost' => 1000,
        ])->assertRedirect();

        $request = StockMoveRequestModel::query()->firstOrFail();

        $this->actingAs($this->user('kho@erp.local'))
            ->post('/inventory/stock-requests/'.$request->id.'/approve')
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(0.0, $this->stockQty($product->id, $warehouse->id));
    }

    public function test_giam_doc_duyet_duoc_phieu(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct('SP-MOVE-6');

        $this->actingAs($this->user('kho@erp.local'))->post('/inventory/stock/move', [
            'type' => 'receive', 'product_id' => $product->id,
            'warehouse_id' => $warehouse->id, 'qty' => 3, 'unit_cost' => 1000,
        ])->assertRedirect();

        $request = StockMoveRequestModel::query()->firstOrFail();

        $this->actingAs($this->user('giamdoc@erp.local'))
            ->post('/inventory/stock-requests/'.$request->id.'/approve')
            ->assertRedirect();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(3.0, $this->stockQty($product->id, $warehouse->id));
    }

    public function test_duyet_phieu_da_xu_ly_khong_ghi_ton_2_lan(): void
    {
        $this->seedBase();
        $warehouse = $this->createWarehouse();
        $product = $this->createProduct('SP-MOVE-7');

        $this->actingAs($this->user('kho@erp.local'))->post('/inventory/stock/move', [
            'type' => 'receive', 'product_id' => $product->id,
            'warehouse_id' => $warehouse->id, 'qty' => 6, 'unit_cost' => 1000,
        ])->assertRedirect();

        $request = StockMoveRequestModel::query()->firstOrFail();

        $this->actingAs($this->admin)->post('/inventory/stock-requests/'.$request->id.'/approve')->assertRedirect();
        $this->assertSame(6.0, $this->stockQty($product->id, $warehouse->id));

        $this->actingAs($this->admin)
            ->post('/inventory/stock-requests/'.$request->id.'/approve')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(6.0, $this->stockQty($product->id, $warehouse->id));
    }
}
