<?php

namespace App\Modules\LoaiKhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreateLoaiKhachHangRequest extends FormRequest
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
      // 🔹 Tên loại khách hàng
      'ten_loai_khach_hang' => 'required|string|max:255',

      // 🔹 Ngưỡng doanh thu (VNĐ)
      'nguong_doanh_thu' => 'required|numeric',

      // 🔹 Giá trị ưu đãi (%) – SỬA THÊM FIELD NÀY
      'gia_tri_uu_dai' => 'required|integer|min:0|max:100',

      // 🔹 Trạng thái
      'trang_thai' => 'required|in:0,1',
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
      'ten_loai_khach_hang.required' => 'Tên loại khách hàng không được để trống',
      'ten_loai_khach_hang.string' => 'Tên loại khách hàng phải là chuỗi',
      'ten_loai_khach_hang.max' => 'Tên loại khách hàng không được vượt quá 255 ký tự',

      'nguong_doanh_thu.required' => 'Ngưỡng doanh thu không được để trống',
      'nguong_doanh_thu.numeric' => 'Ngưỡng doanh thu phải là số',

      // 🔹 Thông báo cho giá trị ưu đãi
      'gia_tri_uu_dai.required' => 'Giá trị ưu đãi không được để trống',
      'gia_tri_uu_dai.integer' => 'Giá trị ưu đãi phải là số nguyên',
      'gia_tri_uu_dai.min' => 'Giá trị ưu đãi không được nhỏ hơn 0%',
      'gia_tri_uu_dai.max' => 'Giá trị ưu đãi không được lớn hơn 100%',

      'trang_thai.required' => 'Trạng thái không được để trống',
      'trang_thai.in' => 'Trạng thái phải là 0 hoặc 1',
    ];
  }
}
