<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.entry.create');
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.partner_type' => ['nullable', 'in:customer,supplier,employee'],
            'lines.*.partner_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'entry_date.required' => 'Ngày chứng từ là bắt buộc.',
            'lines.required' => 'Bút toán phải có ít nhất 2 dòng.',
            'lines.min' => 'Bút toán phải có ít nhất 2 dòng.',
            'lines.*.account_id.required' => 'Mỗi dòng phải chọn tài khoản.',
            'lines.*.account_id.exists' => 'Tài khoản không tồn tại.',
            'lines.*.debit.min' => 'Số tiền Nợ không được âm.',
            'lines.*.credit.min' => 'Số tiền Có không được âm.',
        ];
    }

    /** Mỗi dòng chỉ được có Nợ hoặc Có (không vừa Nợ vừa Có). */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('lines', []) as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'Một dòng không thể vừa có Nợ vừa có Có.');
                }

                if ($debit <= 0 && $credit <= 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'Mỗi dòng phải có số tiền Nợ hoặc Có lớn hơn 0.');
                }
            }
        });
    }
}
