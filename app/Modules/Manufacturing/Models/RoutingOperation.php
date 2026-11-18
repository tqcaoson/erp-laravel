<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;

/** Một công đoạn trong quy trình sản xuất. */
class RoutingOperation extends BaseModel
{
    protected $fillable = [
        'company_id', 'routing_id', 'work_center_id', 'sequence', 'operation_name', 'standard_minutes',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'standard_minutes' => 'decimal:2',
        ];
    }

    public function routing()
    {
        return $this->belongsTo(Routing::class);
    }

    public function workCenter()
    {
        return $this->belongsTo(WorkCenter::class);
    }
}
