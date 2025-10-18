<?php

namespace App\Modules\CongThucSanXuat\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreateCongThucSanXuatRequest extends FormRequest
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
      // Thêm các quy tắc validation cho CongThucSanXuat ở đây
      'san_pham_id' => 'required|exists:san_phams,id',
      'don_vi_tinh_id' => 'required|exists:don_vi_tinhs,id',
      'so_luong' => 'required|integer',
      'ghi_chu' => 'nullable|string',
      'trang_thai' => 'required|in:0,1',
      'chi_tiet_cong_thucs' => 'required|array',
      'chi_tiet_cong_thucs.*.san_pham_id' => 'required|exists:san_phams,id',
      'chi_tiet_cong_thucs.*.don_vi_tinh_id' => 'required|exists:don_vi_tinhs,id',
      'chi_tiet_cong_thucs.*.so_luong' => 'required|integer',
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
      'san_pham_id.required' => 'Sản phẩm là bắt buộc',
      'san_pham_id.exists' => 'Sản phẩm không tồn tại',
      'don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'don_vi_tinh_id.exists' => 'Đơn vị tính không tồn tại',
      'so_luong.required' => 'Số lượng là bắt buộc',
      'so_luong.integer' => 'Số lượng phải là số nguyên',
      'ghi_chu.string' => 'Ghi chú phải là chuỗi',
      'trang_thai.required' => 'Trạng thái là bắt buộc',
      'chi_tiet_cong_thucs.required' => 'Chi tiết công thức là bắt buộc',
      'chi_tiet_cong_thucs.array' => 'Chi tiết công thức phải là mảng',
      'chi_tiet_cong_thucs.*.san_pham_id.required' => 'Sản phẩm là bắt buộc',
      'chi_tiet_cong_thucs.*.san_pham_id.exists' => 'Sản phẩm không tồn tại',
      'chi_tiet_cong_thucs.*.don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'chi_tiet_cong_thucs.*.don_vi_tinh_id.exists' => 'Đơn vị tính không tồn tại',
      'chi_tiet_cong_thucs.*.so_luong.required' => 'Số lượng là bắt buộc',
      'chi_tiet_cong_thucs.*.so_luong.integer' => 'Số lượng phải là số nguyên',
    ];
  }
}