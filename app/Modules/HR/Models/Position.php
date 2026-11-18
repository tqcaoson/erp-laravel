<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Chức danh (kèm cấp bậc nếu có). */
class Position extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'level'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
