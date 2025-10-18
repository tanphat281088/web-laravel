<?php

namespace App\Modules\NhaCungCap\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNhaCungCapRequest extends FormRequest
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
      // Thêm các quy tắc validation cho cập nhật NhaCungCap ở đây
      'ma_nha_cung_cap' => 'sometimes|required|string|max:255|unique:nha_cung_caps,ma_nha_cung_cap,' . $this->id,
      'ten_nha_cung_cap' => 'sometimes|required|string|max:255',
      'so_dien_thoai' => 'sometimes|required|string|max:255|unique:nha_cung_caps,so_dien_thoai,' . $this->id,
      'email' => 'sometimes|required|string|max:255|unique:nha_cung_caps,email,' . $this->id,
      'dia_chi' => 'sometimes|nullable|string|max:255',
      'ma_so_thue' => 'sometimes|required|string|max:255|unique:nha_cung_caps,ma_so_thue,' . $this->id,
      'ngan_hang' => 'sometimes|nullable|string|max:255',
      'so_tai_khoan' => 'sometimes|nullable|string|max:255',
      'ghi_chu' => 'sometimes|nullable|string',
      'trang_thai' => 'sometimes|required|boolean',
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
      'ma_nha_cung_cap.required' => 'Mã nhà cung cấp là bắt buộc',
      'ma_nha_cung_cap.max' => 'Mã nhà cung cấp không được vượt quá 255 ký tự',
      'ma_nha_cung_cap.unique' => 'Mã nhà cung cấp đã tồn tại',
      'ten_nha_cung_cap.required' => 'Tên nhà cung cấp là bắt buộc',
      'ten_nha_cung_cap.max' => 'Tên nhà cung cấp không được vượt quá 255 ký tự',
      'so_dien_thoai.unique' => 'Số điện thoại đã tồn tại',
      'email.unique' => 'Email đã tồn tại',
      'ma_so_thue.unique' => 'Mã số thuế đã tồn tại',
      'so_dien_thoai.required' => 'Số điện thoại là bắt buộc',
      'so_dien_thoai.max' => 'Số điện thoại không được vượt quá 255 ký tự',
      'email.required' => 'Email là bắt buộc',
      'email.max' => 'Email không được vượt quá 255 ký tự',
      'ma_so_thue.required' => 'Mã số thuế là bắt buộc',
      'ma_so_thue.max' => 'Mã số thuế không được vượt quá 255 ký tự',
      'ngan_hang.max' => 'Ngân hàng không được vượt quá 255 ký tự',
      'so_tai_khoan.max' => 'Số tài khoản không được vượt quá 255 ký tự',
      'ghi_chu.max' => 'Ghi chú không được vượt quá 65535 ký tự',
      'trang_thai.required' => 'Trạng thái là bắt buộc',
    ];
  }
}