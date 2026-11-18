<?php

namespace App\Modules\HR\Requests;

use App\Core\Services\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate tạo kỳ lương mới (1 kỳ/tháng, period dạng YYYY-MM). */
class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('hr.payroll.run');
    }

    public function rules(): array
    {
        return [
            'period' => [
                'required',
                'date_format:Y-m',
                Rule::unique('payroll_runs', 'period')->where('company_id', CurrentCompany::id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'period.date_format' => 'Kỳ lương phải có dạng tháng/năm (YYYY-MM).',
            'period.unique' => 'Kỳ lương này đã tồn tại.',
        ];
    }
}
