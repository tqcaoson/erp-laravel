<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiscalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.period.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', Rule::unique('fiscal_periods', 'code')->where('company_id', \App\Core\Services\CurrentCompany::id())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã kỳ phải có dạng YYYY-MM (ví dụ 2026-01).',
            'code.unique' => 'Kỳ kế toán này đã tồn tại.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau ngày bắt đầu.',
        ];
    }
}
