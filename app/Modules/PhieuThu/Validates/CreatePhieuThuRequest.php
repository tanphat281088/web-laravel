<?php

namespace App\Modules\PhieuThu\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreatePhieuThuRequest extends FormRequest
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
      // Thêm các quy tắc validation cho PhieuThu ở đây
      'ma_phieu_thu' => 'required|unique:phieu_thus,ma_phieu_thu',
      'ngay_thu' => 'required|date',
      'loai_phieu_thu' => 'required|in:1,2,3,4',
      'khach_hang_id' => 'required_if:loai_phieu_thu,2|exists:khach_hangs,id',
      'don_hang_id' => 'required_if:loai_phieu_thu,1|exists:don_hangs,id',
      'don_hang_ids' => 'nullable|array',
      'so_tien' => 'required|integer|min:0',
      'nguoi_tra' => 'nullable|string|max:255',
      'phuong_thuc_thanh_toan' => 'required|in:1,2',
      'so_tai_khoan' => 'nullable|string|max:255',
      'ngan_hang' => 'nullable|string|max:255',
      'ly_do_thu' => 'nullable|string',
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
      'ma_phieu_thu.required' => 'Mã phiếu thu là bắt buộc',
      'ma_phieu_thu.unique' => 'Mã phiếu thu đã tồn tại',
      'ngay_thu.required' => 'Ngày thu là bắt buộc',
      'loai_phieu_thu.required' => 'Loại phiếu thu là bắt buộc',
      'loai_phieu_thu.in' => 'Loại phiếu thu không hợp lệ',
      'khach_hang_id.required_if' => 'Khách hàng là bắt buộc khi loại phiếu thu là 2',
      'khach_hang_id.exists' => 'Khách hàng không tồn tại',
      'don_hang_id.required_if' => 'Đơn hàng là bắt buộc khi loại phiếu thu là 1',
      'don_hang_id.exists' => 'Đơn hàng không tồn tại',
      'so_tien.required' => 'Số tiền là bắt buộc',
      'so_tien.integer' => 'Số tiền phải là số nguyên',
      'so_tien.min' => 'Số tiền phải lớn hơn 0',
      'nguoi_tra.string' => 'Người trả tiền phải là chuỗi',
      'nguoi_tra.max' => 'Người trả tiền không được vượt quá 255 ký tự',
      'phuong_thuc_thanh_toan.required' => 'Phương thức thanh toán là bắt buộc',
      'phuong_thuc_thanh_toan.in' => 'Phương thức thanh toán không hợp lệ',
      'so_tai_khoan.string' => 'Số tài khoản phải là chuỗi',
      'so_tai_khoan.max' => 'Số tài khoản không được vượt quá 255 ký tự',
      'ngan_hang.string' => 'Ngân hàng phải là chuỗi',
      'ngan_hang.max' => 'Ngân hàng không được vượt quá 255 ký tự',
      'ly_do_thu.string' => 'Lý do thu phải là chuỗi',
      'ghi_chu.string' => 'Ghi chú phải là chuỗi',
    ];
  }
}