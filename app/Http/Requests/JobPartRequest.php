<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class JobPartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'job_order_id' => 'required|uuid|exists:job_orders,id',
            'part_id' => 'required|uuid|exists:parts,id',

            'is_warranty' => 'required|boolean',

            'qty' => 'required|integer|min:1',
            'qty_issued' => 'nullable|integer|min:0',
            'qty_actual_use' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'job_order_id.required' => 'Cần có mã JobOrder',
            'job_order_id.uuid' => 'JobOrder không hợp lệ',
            'job_order_id.exists' => 'JobOrder không tồn tại',

            'part_id.required' => 'Cần chọn linh kiện',
            'part_id.exists' => 'Linh kiện không tồn tại',

            'is_warranty.required' => 'Trạng thái bảo hành là bắt buộc',
            'is_warranty.boolean' => 'Bảo hành phải là true/false',

            'qty.required' => 'Cần nhập số lượng',
            'qty.integer' => 'Số lượng phải là số nguyên',
            'qty.min' => 'Số lượng tối thiểu là 1'
        ];
    }
}
