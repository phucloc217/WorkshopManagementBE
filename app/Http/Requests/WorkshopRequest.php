<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class WorkshopRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422)
        );
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
                Rule::unique('workshops')->ignore($id)->where(function ($query) {
                    return $query->whereRaw(
                        'LOWER(workshop_code) = ?',
                        [strtolower($this->workshop_code)]
                    );
                }),
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
