<?php

namespace App\Modules\PhieuXuatKho\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhieuXuatKhoRequest extends FormRequest
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
      // Thêm các quy tắc validation cho cập nhật PhieuXuatKho ở đây
      'ma_phieu_xuat_kho' => 'sometimes|required|unique:phieu_xuat_khos,ma_phieu_xuat_kho,' . $this->id,
      'loai_phieu_xuat' => 'sometimes|required|in:1,2,3',
      'ngay_xuat_kho' => 'sometimes|required|date',
      'nguoi_nhan_hang' => 'sometimes|nullable|string',
      'so_dien_thoai_nguoi_nhan_hang' => 'sometimes|nullable|string',
      'don_hang_id' => 'sometimes|nullable|exists:don_hangs,id',
      'san_xuat_id' => 'sometimes|nullable|exists:san_xuats,id',
      'ly_do_huy' => 'sometimes|nullable|string',
      'ghi_chu' => 'sometimes|nullable|string',
      'danh_sach_san_pham' => 'sometimes|required|array',
      'danh_sach_san_pham.*.san_pham_id' => 'sometimes|required|exists:san_phams,id',
      'danh_sach_san_pham.*.don_vi_tinh_id' => 'sometimes|required|exists:don_vi_tinhs,id',
      'danh_sach_san_pham.*.so_luong' => 'sometimes|required|integer',
      'danh_sach_san_pham.*.ma_lo_san_pham' => 'sometimes|required|string|exists:kho_tongs,ma_lo_san_pham',
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
      'ma_phieu_xuat_kho.required' => 'Mã phiếu xuất kho là bắt buộc',
      'ma_phieu_xuat_kho.unique' => 'Mã phiếu xuất kho đã tồn tại',
      'loai_phieu_xuat.required' => 'Loại phiếu xuất kho là bắt buộc',
      'loai_phieu_xuat.in' => 'Loại phiếu xuất kho không hợp lệ',
      'ngay_xuat_kho.required' => 'Ngày xuất kho là bắt buộc',
      'ngay_xuat_kho.date' => 'Ngày xuất kho không hợp lệ',
      'ghi_chu.string' => 'Ghi chú phải là chuỗi',
      'don_hang_id.exists' => 'Đơn hàng không tồn tại',
      'san_xuat_id.exists' => 'Sản xuất không tồn tại',
      'ly_do_huy.string' => 'Lý do hủy phải là chuỗi',
      'so_dien_thoai_nguoi_nhan_hang.string' => 'Số điện thoại người nhận hàng phải là chuỗi',
      'nguoi_nhan_hang.string' => 'Người nhận hàng phải là chuỗi',
      'danh_sach_san_pham.required' => 'Danh sách sản phẩm là bắt buộc',
      'danh_sach_san_pham.array' => 'Danh sách sản phẩm phải là một mảng',
      'danh_sach_san_pham.*.san_pham_id.required' => 'ID sản phẩm là bắt buộc',
      'danh_sach_san_pham.*.san_pham_id.exists' => 'Sản phẩm không tồn tại',
      'danh_sach_san_pham.*.don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'danh_sach_san_pham.*.don_vi_tinh_id.exists' => 'Đơn vị tính không tồn tại',
      'danh_sach_san_pham.*.so_luong.required' => 'Số lượng là bắt buộc',
      'danh_sach_san_pham.*.ma_lo_san_pham.required' => 'Mã loại sản phẩm là bắt buộc',
      'danh_sach_san_pham.*.ma_lo_san_pham.string' => 'Mã loại sản phẩm phải là chuỗi',
    ];
  }
}