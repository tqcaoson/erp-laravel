<?php

namespace Tests\Feature\Concerns;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\GoodsReceiptService;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Support\Facades\DB;

/**
 * Fixture mua hàng dùng chung: nhà cung cấp, đơn mua đã duyệt,
 * phiếu nhận đã xác nhận, và helper đọc số dư sổ cái.
 */
trait CreatesProcurementFixtures
{
    protected function createSupplier(string $code = 'NCC-TEST', int $paymentTermDays = 30): Supplier
    {
        return Supplier::create([
            'company_id' => $this->company->id,
            'code' => $code,
            'name' => 'Nhà cung cấp kiểm thử '.$code,
            'payment_term_days' => $paymentTermDays,
            'is_active' => true,
        ]);
    }

    /** Đơn mua 1 dòng, đã duyệt (duyệt PO bắt buộc phải đăng nhập). */
    protected function createApprovedPurchaseOrder(
        Supplier $supplier,
        Product $product,
        float $qty,
        float $unitPrice,
    ): PurchaseOrder {
        $this->actingAs($this->admin);

        $order = app(PurchaseOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
            ]],
        ], $this->company->id);

        return app(PurchaseOrderService::class)->approve($order->id);
    }

    /** Phiếu nhận 1 dòng theo đơn mua, đã xác nhận (tăng tồn + ghi bút toán). */
    protected function createValidatedGoodsReceipt(
        PurchaseOrder $order,
        Supplier $supplier,
        Warehouse $warehouse,
        Product $product,
        float $qty,
        float $unitCost,
    ): GoodsReceipt {
        $poLine = $order->lines()->firstOrFail();

        $receipt = app(GoodsReceiptService::class)->create([
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'purchase_order_line_id' => $poLine->id,
            ]],
        ], $this->company->id);

        return app(GoodsReceiptService::class)->validate($receipt->id);
    }

    /** Tổng Nợ/Có đã ghi sổ của một tài khoản trong công ty kiểm thử. */
    protected function accountTotals(string $code): array
    {
        $row = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $this->company->id)
            ->where('journal_entries.status', 'posted')
            ->where('chart_of_accounts.code', $code)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as debit, COALESCE(SUM(journal_entry_lines.credit), 0) as credit')
            ->first();

        return ['debit' => (float) $row->debit, 'credit' => (float) $row->credit];
    }
}
