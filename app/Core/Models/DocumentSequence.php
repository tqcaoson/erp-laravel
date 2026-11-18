<?php

namespace App\Core\Models;

/** Bộ đếm số chứng từ theo công ty + loại + năm. Xem DocumentNumberingService. */
class DocumentSequence extends BaseModel
{
    protected $fillable = ['company_id', 'document_type', 'year', 'last_number'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
