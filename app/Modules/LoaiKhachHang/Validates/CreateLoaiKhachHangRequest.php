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
      // Thêm các quy tắc validation cho LoaiKhachHang ở đây
      'ten_loai_khach_hang' => 'required|string|max:255',
      'nguong_doanh_thu' => 'required|numeric',
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
      'trang_thai.required' => 'Trạng thái không được để trống',
      'trang_thai.in' => 'Trạng thái phải là 0 hoặc 1',
    ];
  }
}