<?php

namespace App\Modules\Reporting\Models;

use App\Core\Models\BaseModel;

/** Tổng hợp xuất nhập tồn theo ngày / sản phẩm / kho. */
class DailyStockSummary extends BaseModel
{
    protected $table = 'daily_stock_summary';

    protected $fillable = [
        'company_id', 'date', 'product_id', 'warehouse_id',
        'opening_qty', 'in_qty', 'out_qty', 'closing_qty',
    ];

    protected $casts = [
        'date' => 'date',
        'opening_qty' => 'decimal:4',
        'in_qty' => 'decimal:4',
        'out_qty' => 'decimal:4',
        'closing_qty' => 'decimal:4',
    ];
}
