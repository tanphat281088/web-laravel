<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'current_password' => ['required','string'],
            'new_password'     => ['required','string','min:8','max:72'],
            'confirm_password' => ['required','same:new_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'new_password.required'     => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min'          => 'Mật khẩu mới ít nhất 8 ký tự.',
            'confirm_password.same'     => 'Xác nhận mật khẩu không khớp.',
        ];
    }
}
