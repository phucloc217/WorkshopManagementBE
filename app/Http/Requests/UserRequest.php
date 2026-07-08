<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        $id = $this->route('user');
        return [

            'phone' => [
                'required',
                'string',
                Rule::unique('users', 'phone')->ignore($id),
            ],
            'password' => 'nullable|string|min:6',
            'name' => 'required|string',
            'is_active' => 'boolean',
            'workshop_id' => 'exists:workshops,id'
        ];
    }
    public function messages()
    {
        return [
            'phone.required' => 'Bạn phải nhập số điện thoại',
            'phone.unique' => 'Số điện thoại đã tồn tại',
            'name.required' => 'Bạn phải nhập tên người dùng',
            'is_active.boolean' => 'Trạng thái không hợp lệ'
        ];
    }
}
