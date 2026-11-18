<?php

namespace App\Modules\Reporting\Models;

use App\Core\Models\BaseModel;

/** Tổng hợp doanh thu theo ngày (bảng summary, cập nhật bằng job). */
class DailySalesSummary extends BaseModel
{
    protected $table = 'daily_sales_summary';

    protected $fillable = [
        'company_id', 'date', 'total_revenue', 'total_orders', 'total_cogs',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_orders' => 'integer',
        'total_cogs' => 'decimal:2',
    ];
}
