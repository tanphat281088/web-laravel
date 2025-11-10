<?php

namespace App\Modules\KhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateKhachHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Danh sách kênh hợp lệ từ config
        $kenhOptions = (array) config('kenh_lien_he.options', []);

        return [
            'ten_khach_hang' => 'required|string|max:255',

            // EMAIL: KHÔNG BẮT BUỘC
            'email'          => 'nullable|email|max:255|unique:khach_hangs,email',

            'so_dien_thoai'  => 'required|string|max:255|unique:khach_hangs,so_dien_thoai',
          'dia_chi'        => 'nullable|string|max:255',

            'ghi_chu'        => 'nullable|string|max:255',

            // Kênh liên hệ: BẮT BUỘC + phải nằm trong danh sách cố định
            'kenh_lien_he'   => [
                'required',
                'string',
                'max:191',
                Rule::in($kenhOptions),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ten_khach_hang.required' => 'Tên khách hàng là bắt buộc',
            'ten_khach_hang.max'      => 'Tên khách hàng không được vượt quá 255 ký tự',

            'email.email'             => 'Email không hợp lệ',
            'email.max'               => 'Email không được vượt quá 255 ký tự',
            'email.unique'            => 'Email đã tồn tại',

            'so_dien_thoai.required'  => 'Số điện thoại là bắt buộc',
            'so_dien_thoai.max'       => 'Số điện thoại không được vượt quá 255 ký tự',
            'so_dien_thoai.unique'    => 'Số điện thoại đã tồn tại',

            'dia_chi.required'        => 'Địa chỉ là bắt buộc',

            'kenh_lien_he.required'   => 'Vui lòng chọn Kênh liên hệ',
            'kenh_lien_he.max'        => 'Kênh liên hệ không được vượt quá 191 ký tự',
            'kenh_lien_he.in'         => 'Kênh liên hệ không hợp lệ (phải chọn trong danh sách)',
        ];
    }
}
