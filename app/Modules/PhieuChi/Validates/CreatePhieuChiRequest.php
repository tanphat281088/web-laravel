<?php

namespace App\Modules\PhieuChi\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreatePhieuChiRequest extends FormRequest
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
      // Thêm các quy tắc validation cho PhieuChi ở đây
      'ma_phieu_chi' => 'required|string|max:255|unique:phieu_chis,ma_phieu_chi',
      'ngay_chi' => 'required|date',
      'loai_phieu_chi' => 'required|integer|in:1,2,3,4',
      'nha_cung_cap_id' => 'nullable|integer',
      'phieu_nhap_kho_ids' => 'nullable|array',
      'phieu_nhap_kho_id' => 'nullable|integer',
      'so_tien' => 'required|integer',
      'nguoi_nhan' => 'nullable|string',
      'phuong_thuc_thanh_toan' => 'required|integer|in:1,2',
      'so_tai_khoan' => 'nullable|string',
      'ngan_hang' => 'nullable|string',
      'ly_do_chi' => 'nullable|string',
      'ghi_chu' => 'nullable|string',
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
      'ma_phieu_chi.required' => 'Mã phiếu chi là bắt buộc',
      'ma_phieu_chi.max' => 'Mã phiếu chi không được vượt quá 255 ký tự',
      'ma_phieu_chi.unique' => 'Mã phiếu chi đã tồn tại',
      'ngay_chi.required' => 'Ngày chi là bắt buộc',
      'ngay_chi.date' => 'Ngày chi không hợp lệ',
      'loai_phieu_chi.required' => 'Loại phiếu chi là bắt buộc',
      'loai_phieu_chi.integer' => 'Loại phiếu chi phải là số nguyên',
      'loai_phieu_chi.in' => 'Loại phiếu chi phải là 1, 2, 3 hoặc 4',
      'nha_cung_cap_id.integer' => 'Nhà cung cấp phải là số nguyên',
      'phieu_nhap_kho_id.integer' => 'Phiếu nhập kho phải là số nguyên',
      'phieu_nhap_kho_ids.array' => 'Phiếu nhập kho phải là mảng',
      'so_tien.required' => 'Số tiền là bắt buộc',
      'so_tien.integer' => 'Số tiền phải là số nguyên',
      'nguoi_nhan.string' => 'Người nhận phải là chuỗi',
      'phuong_thuc_thanh_toan.required' => 'Phương thức thanh toán là bắt buộc',
      'phuong_thuc_thanh_toan.integer' => 'Phương thức thanh toán phải là số nguyên',
      'phuong_thuc_thanh_toan.in' => 'Phương thức thanh toán phải là 1 hoặc 2',
      'so_tai_khoan.string' => 'Số tài khoản phải là chuỗi',
      'ngan_hang.string' => 'Ngân hàng phải là chuỗi',
      'ly_do_chi.string' => 'Lý do chi phải là chuỗi',
      'ghi_chu.string' => 'Ghi chú phải là chuỗi',
    ];
  }
}