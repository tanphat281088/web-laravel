<?php

namespace App\Modules\QuanLyBanHang\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuanLyBanHangRequest extends FormRequest
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
      // Thêm các quy tắc validation cho cập nhật QuanLyBanHang ở đây
      'ma_don_hang' => 'sometimes|required|string|max:255|unique:don_hangs,ma_don_hang,' . $this->id,
      'ngay_tao_don_hang' => 'sometimes|required|date',
      'loai_khach_hang' => 'sometimes|required|integer|in:0,1',
      'khach_hang_id' => 'sometimes|nullable|integer|exists:khach_hangs,id',
      'ten_khach_hang' => 'sometimes|nullable|string|max:255',
      'so_dien_thoai' => 'sometimes|nullable|string|max:255',
      'dia_chi_giao_hang' => 'sometimes|required|string',
      'giam_gia' => 'sometimes|required|integer',
      'chi_phi' => 'sometimes|required|integer',
      'loai_thanh_toan' => 'sometimes|required|integer|in:0,1,2',
      'so_tien_da_thanh_toan' => 'sometimes|nullable|integer',
      'ghi_chu' => 'sometimes|nullable|string|max:255',

      'danh_sach_san_pham' => 'sometimes|required|array',
      'danh_sach_san_pham.*.san_pham_id' => 'sometimes|required|integer|exists:san_phams,id',
      'danh_sach_san_pham.*.don_vi_tinh_id' => 'sometimes|required|integer|exists:don_vi_tinhs,id',
      'danh_sach_san_pham.*.so_luong' => 'sometimes|required|integer|min:1',

      // ✅ MỚI: yêu cầu loại giá nếu có chỉnh danh_sach_san_pham
      'danh_sach_san_pham.*.loai_gia' => 'sometimes|required|integer|in:1,2',
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
      'ma_don_hang.required' => 'Mã đơn hàng là bắt buộc',
      'ma_don_hang.max' => 'Mã đơn hàng không được vượt quá 255 ký tự',
      'ma_don_hang.unique' => 'Mã đơn hàng đã tồn tại',
      'ngay_tao_don_hang.required' => 'Ngày tạo đơn hàng là bắt buộc',
      'ngay_tao_don_hang.date' => 'Ngày tạo đơn hàng không hợp lệ',
      'loai_khach_hang.required' => 'Loại khách hàng là bắt buộc',
      'loai_khach_hang.integer' => 'Loại khách hàng phải là số',
      'loai_khach_hang.in' => 'Loại khách hàng phải là 0 hoặc 1',
      'khach_hang_id.integer' => 'Khách hàng phải là số',
      'khach_hang_id.exists' => 'Khách hàng không tồn tại',
      'ten_khach_hang.string' => 'Tên khách hàng phải là chuỗi',
      'ten_khach_hang.max' => 'Tên khách hàng không được vượt quá 255 ký tự',
      'so_dien_thoai.string' => 'Số điện thoại phải là chuỗi',
      'so_dien_thoai.max' => 'Số điện thoại không được vượt quá 255 ký tự',
      'dia_chi_giao_hang.required' => 'Địa chỉ giao hàng là bắt buộc',
      'dia_chi_giao_hang.string' => 'Địa chỉ giao hàng phải là chuỗi',
      'giam_gia.required' => 'Giảm giá là bắt buộc',
      'giam_gia.integer' => 'Giảm giá phải là số',
      'chi_phi.required' => 'Chi phí là bắt buộc',
      'chi_phi.integer' => 'Chi phí phải là số',
      'loai_thanh_toan.required' => 'Loại thanh toán là bắt buộc',
      'loai_thanh_toan.integer' => 'Loại thanh toán phải là số',
      'loai_thanh_toan.in' => 'Loại thanh toán phải là 0, 1 hoặc 2',
      'so_tien_da_thanh_toan.integer' => 'Số tiền đã thanh toán phải là số',
      'ghi_chu.string' => 'Ghi chú phải là chuỗi',
      'ghi_chu.max' => 'Ghi chú không được vượt quá 255 ký tự',

      'danh_sach_san_pham.required' => 'Danh sách sản phẩm là bắt buộc',
      'danh_sach_san_pham.array' => 'Danh sách sản phẩm phải là một mảng',
      'danh_sach_san_pham.*.san_pham_id.required' => 'ID sản phẩm là bắt buộc',
      'danh_sach_san_pham.*.san_pham_id.integer' => 'ID sản phẩm phải là số',
      'danh_sach_san_pham.*.san_pham_id.exists' => 'Sản phẩm không tồn tại',
      'danh_sach_san_pham.*.don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'danh_sach_san_pham.*.don_vi_tinh_id.integer' => 'Đơn vị tính phải là số',
      'danh_sach_san_pham.*.don_vi_tinh_id.exists' => 'Đơn vị tính không tồn tại',
      'danh_sach_san_pham.*.so_luong.required' => 'Số lượng là bắt buộc',
      'danh_sach_san_pham.*.so_luong.integer' => 'Số lượng phải là số',
      'danh_sach_san_pham.*.so_luong.min' => 'Số lượng phải lớn hơn 0',

      // ✅ MỚI:
      'danh_sach_san_pham.*.loai_gia.required' => 'Vui lòng chọn loại giá',
      'danh_sach_san_pham.*.loai_gia.integer'  => 'Loại giá phải là số',
      'danh_sach_san_pham.*.loai_gia.in'       => 'Loại giá không hợp lệ',
    ];
  }
}
