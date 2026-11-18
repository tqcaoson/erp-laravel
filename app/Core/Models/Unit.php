<?php

namespace App\Core\Models;

/** Đơn vị tính dùng chung (cái, hộp, thùng, kg...). company_id = null là dùng chung mọi công ty. */
class Unit extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
