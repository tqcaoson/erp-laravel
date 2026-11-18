<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;

/** Công đoạn sản xuất (sinh từ routing khi bắt đầu lệnh sản xuất). */
class WorkOrder extends BaseModel
{
    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    public const STATUS_LABELS = [
        'pending' => 'Chờ xử lý',
        'in_progress' => 'Đang thực hiện',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
    ];

    protected $fillable = [
        'company_id', 'code', 'manufacturing_order_id', 'work_center_id',
        'operation_name', 'sequence', 'status', 'planned_minutes', 'actual_minutes',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'planned_minutes' => 'decimal:2',
            'actual_minutes' => 'decimal:2',
        ];
    }

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    public function workCenter()
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }
}
