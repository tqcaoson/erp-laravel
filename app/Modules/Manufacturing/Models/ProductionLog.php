<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;

/** Nhật ký sản xuất: sản lượng + phế phẩm ghi nhận theo lệnh/công đoạn. */
class ProductionLog extends BaseModel
{
    protected $fillable = [
        'company_id', 'manufacturing_order_id', 'work_order_id', 'product_id',
        'qty_produced', 'qty_scrap', 'employee_id', 'logged_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty_produced' => 'decimal:4',
            'qty_scrap' => 'decimal:4',
            'logged_at' => 'datetime',
        ];
    }

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** Sản phẩm (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }
}
