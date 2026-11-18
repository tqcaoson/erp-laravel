<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;

/**
 * Lệnh sản xuất.
 * Luồng trạng thái: draft → confirmed → in_progress → completed (hoặc cancelled).
 */
class ManufacturingOrder extends BaseModel
{
    public const STATUSES = ['draft', 'confirmed', 'in_progress', 'completed', 'cancelled'];

    public const STATUS_LABELS = [
        'draft' => 'Nháp',
        'confirmed' => 'Đã xác nhận',
        'in_progress' => 'Đang sản xuất',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
    ];

    protected $fillable = [
        'company_id', 'code', 'product_id', 'bom_id', 'routing_id', 'sales_order_id',
        'warehouse_id', 'qty', 'produced_qty', 'status',
        'planned_start_date', 'planned_end_date', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'produced_qty' => 'decimal:4',
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
        ];
    }

    /** Thành phẩm (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function routing()
    {
        return $this->belongsTo(Routing::class);
    }

    /** Đơn bán gốc (module Sales) — chỉ đọc qua tên class dạng chuỗi. */
    public function salesOrder()
    {
        return $this->belongsTo('App\Modules\Sales\Models\SalesOrder');
    }

    /** Kho xuất nguyên liệu / nhập thành phẩm (module Inventory). */
    public function warehouse()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Warehouse');
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class)->orderBy('sequence');
    }

    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }
}
