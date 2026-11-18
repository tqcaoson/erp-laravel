<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Lịch sử tương tác với khách hàng (gọi điện, email, họp, ghi chú). */
class Activity extends BaseModel
{
    public const TYPES = ['call', 'email', 'meeting', 'note'];

    public const TYPE_LABELS = [
        'call' => 'Cuộc gọi',
        'email' => 'Email',
        'meeting' => 'Cuộc họp',
        'note' => 'Ghi chú',
    ];

    protected $fillable = [
        'company_id', 'customer_id', 'opportunity_id', 'user_id',
        'type', 'subject', 'description', 'activity_date',
    ];

    protected function casts(): array
    {
        return ['activity_date' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
