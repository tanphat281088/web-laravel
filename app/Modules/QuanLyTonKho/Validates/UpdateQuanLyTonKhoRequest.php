<?php

namespace App\Modules\QuanLyTonKho\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuanLyTonKhoRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Thêm các quy tắc validation cho cập nhật QuanLyTonKho ở đây
            'name' => 'sometimes|required|string|max:255',
            // 'description' => 'nullable|string',
            // 'active' => 'boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên QuanLyTonKho là bắt buộc',
            'name.max' => 'Tên QuanLyTonKho không được vượt quá 255 ký tự',
        ];
    }
}
