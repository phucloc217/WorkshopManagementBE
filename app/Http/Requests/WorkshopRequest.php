<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class WorkshopRequest extends FormRequest
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
         $id = $this->route('id') ?? $this->route('workshop')?->id;
        return [
            'workshop_code' => [
                'required',
                'string',
                Rule::unique('workshops', 'workshop_code')->ignore($id),
            ],
            'name' => 'required|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'is_active' => 'boolean'
        ];
    }
    public function messages()
    {
        return [
            'workshop_code.required' => 'Bạn phải nhập mã chi nhánh',
            'workshop_code.unique' => 'Mã chi nhánh đã tồn tại',
            'name.required' => 'Bạn phải nhập tên chi nhánh',
            'is_active.boolean' => 'Trạng thái không hợp lệ'
        ];
    }
}
