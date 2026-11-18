<?php

namespace App\Core\Http\Controllers;

use App\Core\Services\CurrentCompany;
use Illuminate\Http\Request;

/** Đổi công ty đang làm việc ở topbar (đa công ty). */
class CompanyController
{
    public function switch(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $companyId = (int) $validated['company_id'];

        // Chỉ được đổi sang công ty mà user thuộc về.
        if (! $request->user()->companies()->where('companies.id', $companyId)->exists()) {
            abort(403, 'Bạn không thuộc công ty này.');
        }

        CurrentCompany::switchTo($companyId);

        return back();
    }
}
