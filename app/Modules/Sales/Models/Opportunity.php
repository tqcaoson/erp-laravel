<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Cơ hội bán hàng (pipeline theo giai đoạn, hiển thị dạng kanban). */
class Opportunity extends BaseModel
{
    public const STAGES = ['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    public const STAGE_LABELS = [
        'lead' => 'Đầu mối',
        'qualified' => 'Đủ tiêu chuẩn',
        'proposal' => 'Báo giá',
        'negotiation' => 'Đàm phán',
        'won' => 'Thắng',
        'lost' => 'Thua',
    ];

    protected $fillable = [
        'company_id', 'code', 'customer_id', 'lead_id', 'name', 'stage',
        'expected_amount', 'expected_close_date', 'description', 'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'expected_close_date' => 'date',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
