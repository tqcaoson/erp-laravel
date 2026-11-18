<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/**
 * Đơn bán hàng.
 * Luồng trạng thái: draft → pending_approval → confirmed → partially_delivered
 * → delivered → invoiced → closed (hoặc cancelled từ draft/pending_approval/confirmed).
 */
class SalesOrder extends BaseModel
{
    public const STATUSES = [
        'draft', 'pending_approval', 'confirmed', 'partially_delivered',
        'delivered', 'invoiced', 'closed', 'cancelled',
    ];

    public const STATUS_LABELS = [
        'draft' => 'Nháp',
        'pending_approval' => 'Chờ duyệt',
        'confirmed' => 'Đã xác nhận',
        'partially_delivered' => 'Giao một phần',
        'delivered' => 'Đã giao',
        'invoiced' => 'Đã lập hóa đơn',
        'closed' => 'Đã đóng',
        'cancelled' => 'Đã hủy',
    ];

    protected $fillable = [
        'company_id', 'code', 'customer_id', 'quotation_id', 'warehouse_id', 'status',
        'order_date', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'note',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Kho giao hàng (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function warehouse()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Warehouse');
    }

    public function lines()
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    /** Tổng số lượng đã giao trên toàn đơn. */
    public function deliveredQtyTotal(): float
    {
        return (float) $this->lines()->sum('delivered_qty');
    }

    /** Đơn đã giao đủ toàn bộ các dòng hay chưa. */
    public function isFullyDelivered(): bool
    {
        return $this->lines()->whereColumn('delivered_qty', '<', 'qty')->doesntExist()
            && $this->lines()->exists();
    }
}
