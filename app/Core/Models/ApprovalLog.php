<?php

namespace App\Core\Models;

/** Lịch sử từng bước duyệt: ai duyệt, khi nào, đồng ý/từ chối, ghi chú, IP. */
class ApprovalLog extends BaseModel
{
    protected $fillable = [
        'company_id',
        'approval_rule_id',
        'approvable_type',
        'approvable_id',
        'approver_id',
        'level',
        'decision',
        'note',
        'ip_address',
    ];

    public function rule()
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approver_id');
    }

    public function approvable()
    {
        return $this->morphTo();
    }
}
