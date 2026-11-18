<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.account.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('chart_of_accounts', 'code')->where('company_id', \App\Core\Services\CurrentCompany::id())],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã tài khoản là bắt buộc.',
            'code.unique' => 'Mã tài khoản này đã tồn tại trong công ty.',
            'name.required' => 'Tên tài khoản là bắt buộc.',
            'type.in' => 'Loại tài khoản không hợp lệ.',
            'parent_id.exists' => 'Tài khoản cha không tồn tại.',
        ];
    }
}
