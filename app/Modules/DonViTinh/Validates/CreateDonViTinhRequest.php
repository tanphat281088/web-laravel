<?php

namespace App\Modules\DonViTinh\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreateDonViTinhRequest extends FormRequest
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
      // Thêm các quy tắc validation cho DonViTinh ở đây
      'ten_don_vi' => 'required|string|max:255|unique:don_vi_tinhs,ten_don_vi',
      'ky_hieu' => 'nullable|string|max:255|unique:don_vi_tinhs,ky_hieu',
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
      'ten_don_vi.required' => 'Tên DonViTinh là bắt buộc',
      'ten_don_vi.max' => 'Tên DonViTinh không được vượt quá 255 ký tự',
      'ten_don_vi.unique' => 'Tên DonViTinh đã tồn tại',
      'ky_hieu.max' => 'Ký hiệu không được vượt quá 255 ký tự',
      'ky_hieu.unique' => 'Ký hiệu đã tồn tại',
      'trang_thai.required' => 'Trạng thái là bắt buộc',
      'trang_thai.in' => 'Trạng thái phải là 0 hoặc 1',
    ];
  }
}