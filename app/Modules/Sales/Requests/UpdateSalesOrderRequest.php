<?php

namespace App\Modules\Sales\Requests;

/**
 * Validate sửa đơn bán hàng (chỉ khi còn nháp).
 * Kế thừa toàn bộ quy tắc tạo đơn; thêm `lines.*.id` để service đồng bộ
 * dòng theo id: có id thì cập nhật dòng cũ, không id thì thêm dòng mới,
 * dòng cũ vắng mặt trong request thì xóa.
 */
class UpdateSalesOrderRequest extends StoreSalesOrderRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'lines.*.id' => ['nullable', 'integer', 'exists:sales_order_lines,id'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'lines.*.id.exists' => 'Dòng sản phẩm không tồn tại.',
        ]);
    }
}
