<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Đầu mối (khách hàng tiềm năng chưa trở thành khách hàng chính thức). */
class Lead extends BaseModel
{
    public const STATUSES = ['new', 'contacted', 'qualified', 'lost'];

    public const STATUS_LABELS = [
        'new' => 'Mới',
        'contacted' => 'Đã liên hệ',
        'qualified' => 'Đủ tiêu chuẩn',
        'lost' => 'Đã mất',
    ];

    protected $fillable = [
        'company_id', 'name', 'company_name', 'phone', 'email', 'source', 'status', 'note',
    ];

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
