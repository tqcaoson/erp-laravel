<?php

namespace Tests\Feature;

use App\Core\Contracts\StockServiceInterface;
use App\Modules\Inventory\Models\StockQuant;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/** Xuất kho vượt tồn khả dụng phải bị từ chối và tồn không đổi. */
class OverstockIssueTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    public function test_xuất_kho_vượt_tồn_bị_từ_chối_và_tồn_không_đổi(): void
    {
        $this->seedBaseData();

        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        /** @var StockServiceInterface $stock */
        $stock = app(StockServiceInterface::class);

        $stock->receive($product->id, $warehouse->id, null, 10, 50000, self::class, 1);

        $this->assertSame(10.0, $stock->availableQty($product->id, $warehouse->id));

        try {
            $stock->issue($product->id, $warehouse->id, null, 20, self::class, 2);
            $this->fail('Xuất vượt tồn phải ném DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Không đủ tồn kho', $e->getMessage());
        }

        // Tồn không đổi sau lần xuất thất bại.
        $this->assertSame(10.0, $stock->availableQty($product->id, $warehouse->id));
        $this->assertSame(10.0, (float) StockQuant::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('qty'));
    }

    public function test_xuất_kho_trong_giới_hạn_tồn_thành_công(): void
    {
        $this->seedBaseData();

        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        /** @var StockServiceInterface $stock */
        $stock = app(StockServiceInterface::class);

        $stock->receive($product->id, $warehouse->id, null, 10, 50000, self::class, 1);
        $stock->issue($product->id, $warehouse->id, null, 4, self::class, 2);

        $this->assertSame(6.0, $stock->availableQty($product->id, $warehouse->id));
    }
}
