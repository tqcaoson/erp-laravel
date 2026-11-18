<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Phiếu giao hàng (tạo từ đơn bán, thủ kho xác nhận khi xuất kho thật). */
class DeliveryOrder extends BaseModel
{
    public const STATUSES = ['draft', 'validated', 'cancelled'];

    public const STATUS_LABELS = [
        'draft' => 'Nháp',
        'validated' => 'Đã xác nhận giao',
        'cancelled' => 'Đã hủy',
    ];

    protected $fillable = [
        'company_id', 'code', 'sales_order_id', 'warehouse_id',
        'status', 'delivery_date', 'note',
    ];

    protected function casts(): array
    {
        return ['delivery_date' => 'date'];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** Kho xuất hàng (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function warehouse()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Warehouse');
    }

    public function lines()
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }
}
