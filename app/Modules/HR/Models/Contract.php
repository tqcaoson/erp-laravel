<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Hợp đồng lao động. */
class Contract extends BaseModel
{
    public const TYPE_PROBATION = 'probation';
    public const TYPE_FIXED_TERM = 'fixed_term';
    public const TYPE_INDEFINITE = 'indefinite';

    protected $fillable = [
        'company_id', 'employee_id', 'code', 'type',
        'start_date', 'end_date', 'salary', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
