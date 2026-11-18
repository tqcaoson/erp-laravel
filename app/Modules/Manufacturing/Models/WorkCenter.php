<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;

/** Trung tâm sản xuất (máy/chuyền/tổ sản xuất). */
class WorkCenter extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'cost_per_hour', 'is_active'];

    protected function casts(): array
    {
        return [
            'cost_per_hour' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function routingOperations()
    {
        return $this->hasMany(RoutingOperation::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }
}
