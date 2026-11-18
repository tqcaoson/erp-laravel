<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;

/**
 * Đơn mua hàng (PO).
 * Luồng: draft → pending_approval → approved → partially_received → received → closed,
 * hoặc cancelled (chỉ khi chưa nhận hàng).
 */
class PurchaseOrder extends BaseModel
{
    protected $fillable = [
        'company_id', 'code', 'supplier_id', 'purchase_request_id', 'status',
        'order_date', 'expected_date', 'subtotal', 'tax_amount', 'total_amount', 'note',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /** Tổng số lượng đã nhận trên toàn bộ đơn. */
    public function totalReceivedQty(): float
    {
        return (float) $this->lines()->sum('received_qty');
    }
}
