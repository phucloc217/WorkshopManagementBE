<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'job_order_id' => 'required',
            'task_name'=>'required',
            'is_warranty'=>'required'
        ];
    }
     public function messages()
    {
        return [
            'job_order_id.required' => 'Cần có mã JobOrder',
            'task_name.required' => 'Tên công việc là bắt buộc',
            'is_warranty.required' => 'Trạng thái bảo hành của công việc là bắt buộc'
        ];
    }
}
