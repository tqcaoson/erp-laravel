<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Ca làm việc. */
class Shift extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'start_time', 'end_time'];

    protected $casts = ['start_time' => 'string', 'end_time' => 'string'];

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
