<?php

namespace App\Modules\SanXuat\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSanXuatRequest extends FormRequest
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
      // Thêm các quy tắc validation cho cập nhật SanXuat ở đây
      'ma_lo_san_xuat' => 'sometimes|required|string|max:255|unique:san_xuats,ma_lo_san_xuat,' . $this->id,
      'san_pham_id' => 'sometimes|required|exists:san_phams,id',
      'don_vi_tinh_id' => 'sometimes|required|exists:don_vi_tinhs,id',
      'so_luong' => 'sometimes|required|integer',
      'loi_nhuan' => 'sometimes|required|integer',
      'chi_phi_khac' => 'sometimes|required|integer',
      'ghi_chu' => 'sometimes|nullable|string',
      'chi_tiet_cong_thucs' => 'sometimes|required|array',
      'chi_tiet_cong_thucs.*.san_pham_id' => 'sometimes|required|exists:san_phams,id',
      'chi_tiet_cong_thucs.*.don_vi_tinh_id' => 'sometimes|required|exists:don_vi_tinhs,id',
      'chi_tiet_cong_thucs.*.so_luong' => 'sometimes|required|integer',
      'chi_tiet_cong_thucs.*.so_luong_thuc_te' => 'sometimes|required|integer',
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
      'ma_lo_san_xuat.required' => 'Mã lô sản xuất là bắt buộc',
      'ma_lo_san_xuat.max' => 'Mã lô sản xuất không được vượt quá 255 ký tự',
      'ma_lo_san_xuat.unique' => 'Mã lô sản xuất đã tồn tại',
      'san_pham_id.required' => 'Sản phẩm là bắt buộc',
      'san_pham_id.exists' => 'Sản phẩm không tồn tại',
      'don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'don_vi_tinh_id.exists' => 'Đơn vị tính không tồn tại',
      'so_luong.required' => 'Số lượng là bắt buộc',
      'so_luong.integer' => 'Số lượng phải là số nguyên',
      'loi_nhuan.required' => 'Lợi nhuận là bắt buộc',
      'loi_nhuan.integer' => 'Lợi nhuận phải là số nguyên',
      'chi_phi_khac.required' => 'Chi phí khác là bắt buộc',
      'chi_phi_khac.integer' => 'Chi phí khác phải là số nguyên',
      'ghi_chu.string' => 'Ghi chú phải là chuỗi',
      'chi_tiet_cong_thucs.required' => 'Chi tiết sản xuất là bắt buộc',
      'chi_tiet_cong_thucs.array' => 'Chi tiết sản xuất phải là mảng',
      'chi_tiet_cong_thucs.*.san_pham_id.required' => 'Nguyên liệu là bắt buộc',
      'chi_tiet_cong_thucs.*.san_pham_id.exists' => 'Nguyên liệu không tồn tại',
      'chi_tiet_cong_thucs.*.don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'chi_tiet_cong_thucs.*.don_vi_tinh_id.exists' => 'Đơn vị tính không tồn tại',
      'chi_tiet_cong_thucs.*.so_luong.required' => 'Số lượng là bắt buộc',
      'chi_tiet_cong_thucs.*.so_luong.integer' => 'Số lượng phải là số nguyên',
      'chi_tiet_cong_thucs.*.so_luong_thuc_te.required' => 'Số lượng thực tế là bắt buộc',
      'chi_tiet_cong_thucs.*.so_luong_thuc_te.integer' => 'Số lượng thực tế phải là số nguyên',
    ];
  }
}