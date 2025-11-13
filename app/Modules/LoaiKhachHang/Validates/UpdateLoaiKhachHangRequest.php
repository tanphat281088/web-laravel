<?php

namespace App\Modules\LoaiKhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoaiKhachHangRequest extends FormRequest
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
      // 🔹 Các field có thể gửi lên khi cập nhật
      'ten_loai_khach_hang' => 'sometimes|required|string|max:255',
      'nguong_doanh_thu'   => 'sometimes|required|numeric',

      // 🔹 THÊM field giá trị ưu đãi (%)
      'gia_tri_uu_dai'     => 'sometimes|required|integer|min:0|max:100',

      'trang_thai'         => 'sometimes|required|in:0,1',
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
      // Có thể để trống hoặc bổ sung nếu anh muốn custom message
    ];
  }
}
