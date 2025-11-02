<?php

namespace App\Modules\QuanLyBanHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuanLyBanHangRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Lấy id đơn hàng từ route/input để áp dụng unique bỏ qua chính nó.
   */
  protected function currentId(): ?int
  {
    // Tuỳ controller: /don-hang/{id}
    return (int)($this->route('id') ?? $this->route('don_hang') ?? $this->input('id') ?? 0);
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    $id = $this->currentId();

    return [
      // ===== Thông tin đơn hàng =====
      'ma_don_hang'       => ['prohibited'], // không cho sửa mã sau khi tạo

      'ngay_tao_don_hang' => ['sometimes','required','date'],
      'dia_chi_giao_hang' => ['sometimes','required','string'],

      // ===== TRẠNG THÁI ĐƠN HÀNG (NEW) =====
      // 0 = Chưa giao, 1 = Đang giao, 2 = Đã giao, 3 = Đã hủy
      'trang_thai_don_hang' => ['sometimes','nullable','integer', Rule::in([0,1,2,3])],

      // ===== Thông tin người nhận =====
      'nguoi_nhan_ten'        => ['sometimes','nullable','string','max:191'],
      'nguoi_nhan_sdt'        => ['sometimes','nullable','string','max:20','regex:/^(0|\+84)\d{8,12}$/'],
      'nguoi_nhan_thoi_gian'  => ['sometimes','nullable','date'],

      // ===== Khách hàng =====
      // 0 = KH hệ thống, 1 = KH tự do
      'loai_khach_hang'   => ['sometimes','required','integer', Rule::in([0,1])],
      'khach_hang_id'     => ['sometimes','nullable','integer','exists:khach_hangs,id'],
      'ten_khach_hang'    => ['sometimes','nullable','string','max:255'],
      'so_dien_thoai'     => ['sometimes','nullable','string','max:255'],

      // ===== Chi phí – giảm trừ =====
      'giam_gia'          => ['sometimes','required','numeric','min:0'],
      'chi_phi'           => ['sometimes','required','numeric','min:0'],

      // ===== Thanh toán =====
      // 0 = Chưa thanh toán, 1 = Thanh toán một phần, 2 = Thanh toán toàn bộ
      'loai_thanh_toan'       => ['sometimes','required','integer', Rule::in([0,1,2])],
      'so_tien_da_thanh_toan' => [
        'sometimes','nullable','numeric','min:0',
        Rule::requiredIf(function () {
          $val = $this->input('loai_thanh_toan');
          return isset($val) && (int)$val === 1;
        }),
      ],

            // ===== Thuế (NEW) =====
      'tax_mode' => ['sometimes', 'integer', Rule::in([0, 1])],
      'vat_rate' => [
        'sometimes', 'nullable', 'numeric', 'min:0', 'max:20',
        Rule::requiredIf(function () {
          $val = $this->input('tax_mode');
          return isset($val) && (int)$val === 1;
        }),
      ],


      // ===== Danh sách sản phẩm =====
      'danh_sach_san_pham'                   => ['sometimes','required','array','min:1'],
      'danh_sach_san_pham.*.san_pham_id'     => ['sometimes','required','integer','exists:san_phams,id'],
      'danh_sach_san_pham.*.don_vi_tinh_id'  => ['sometimes','required','integer','exists:don_vi_tinhs,id'],
      'danh_sach_san_pham.*.so_luong'        => ['sometimes','required','numeric','min:1'],
      // loai_gia: 1 = Đặt ngay, 2 = Đặt trước 3 ngày
      'danh_sach_san_pham.*.loai_gia'        => ['sometimes','required','integer', Rule::in([1,2])],
      'danh_sach_san_pham.*.don_gia'         => ['sometimes','nullable','numeric','min:0'],
      'danh_sach_san_pham.*.thanh_tien'      => ['sometimes','nullable','numeric','min:0'],

      // ===== Khác =====
      'ghi_chu'            => ['sometimes','nullable','string','max:255'],
      'images'             => ['sometimes','nullable','array'],
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
      // Đơn hàng
      'ma_don_hang.prohibited'     => 'Không được phép sửa mã đơn hàng',
      'ngay_tao_don_hang.required' => 'Ngày tạo đơn hàng là bắt buộc',
      'ngay_tao_don_hang.date'     => 'Ngày tạo đơn hàng không hợp lệ',
      'dia_chi_giao_hang.required' => 'Địa chỉ giao hàng là bắt buộc',
      'dia_chi_giao_hang.string'   => 'Địa chỉ giao hàng phải là chuỗi',

      // Trạng thái đơn hàng
      'trang_thai_don_hang.integer' => 'Trạng thái đơn hàng phải là số',
      'trang_thai_don_hang.in'      => 'Trạng thái đơn hàng phải là 0, 1, 2 hoặc 3',

      // Thông tin người nhận
      'nguoi_nhan_ten.string'      => 'Tên người nhận phải là chuỗi',
      'nguoi_nhan_ten.max'         => 'Tên người nhận không được vượt quá 191 ký tự',
      'nguoi_nhan_sdt.string'      => 'SĐT người nhận phải là chuỗi',
      'nguoi_nhan_sdt.max'         => 'SĐT người nhận không được vượt quá 20 ký tự',
      'nguoi_nhan_sdt.regex'       => 'SĐT người nhận không hợp lệ (hỗ trợ 0… hoặc +84…)',
      'nguoi_nhan_thoi_gian.date'  => 'Ngày giờ nhận không hợp lệ',

      // Khách hàng
      'loai_khach_hang.required'   => 'Loại khách hàng là bắt buộc',
      'loai_khach_hang.integer'    => 'Loại khách hàng phải là số',
      'loai_khach_hang.in'         => 'Loại khách hàng phải là 0 hoặc 1',
      'khach_hang_id.integer'      => 'Khách hàng phải là số',
      'khach_hang_id.exists'       => 'Khách hàng không tồn tại',
      'ten_khach_hang.string'      => 'Tên khách hàng phải là chuỗi',
      'ten_khach_hang.max'         => 'Tên khách hàng không được vượt quá 255 ký tự',
      'so_dien_thoai.string'       => 'Số điện thoại phải là chuỗi',
      'so_dien_thoai.max'          => 'Số điện thoại không được vượt quá 255 ký tự',

      // Giảm trừ/Chi phí
      'giam_gia.required'          => 'Giảm giá là bắt buộc',
      'giam_gia.numeric'           => 'Giảm giá phải là số',
      'giam_gia.min'               => 'Giảm giá không được âm',
      'chi_phi.required'           => 'Chi phí là bắt buộc',
      'chi_phi.numeric'            => 'Chi phí phải là số',
      'chi_phi.min'                => 'Chi phí không được âm',

      // Thanh toán
      'loai_thanh_toan.required'   => 'Loại thanh toán là bắt buộc',
      'loai_thanh_toan.integer'    => 'Loại thanh toán phải là số',
      'loai_thanh_toan.in'         => 'Loại thanh toán phải là 0, 1 hoặc 2',
      'so_tien_da_thanh_toan.required_if' => 'Vui lòng nhập số tiền đã thanh toán khi chọn "Thanh toán một phần"',
      'so_tien_da_thanh_toan.numeric'     => 'Số tiền đã thanh toán phải là số',
      'so_tien_da_thanh_toan.min'         => 'Số tiền đã thanh toán không được âm',

      // Sản phẩm
      'danh_sach_san_pham.required' => 'Danh sách sản phẩm là bắt buộc',
      'danh_sach_san_pham.array'    => 'Danh sách sản phẩm phải là một mảng',
      'danh_sach_san_pham.min'      => 'Đơn hàng phải có ít nhất 1 sản phẩm',
      'danh_sach_san_pham.*.san_pham_id.required' => 'ID sản phẩm là bắt buộc',
      'danh_sach_san_pham.*.san_pham_id.integer'  => 'ID sản phẩm phải là số',
      'danh_sach_san_pham.*.san_pham_id.exists'   => 'Sản phẩm không tồn tại',
      'danh_sach_san_pham.*.don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'danh_sach_san_pham.*.don_vi_tinh_id.integer'  => 'Đơn vị tính phải là số',
      'danh_sach_san_pham.*.don_vi_tinh_id.exists'   => 'Đơn vị tính không tồn tại',
      'danh_sach_san_pham.*.so_luong.required'       => 'Số lượng là bắt buộc',
      'danh_sach_san_pham.*.so_luong.numeric'        => 'Số lượng phải là số',
      'danh_sach_san_pham.*.so_luong.min'            => 'Số lượng phải lớn hơn 0',

      // Loại giá
      'danh_sach_san_pham.*.loai_gia.required' => 'Vui lòng chọn loại giá',
      'danh_sach_san_pham.*.loai_gia.integer'  => 'Loại giá phải là số',
      'danh_sach_san_pham.*.loai_gia.in'       => 'Loại giá không hợp lệ',

      // Khác
      'ghi_chu.string' => 'Ghi chú phải là chuỗi',
      'ghi_chu.max'    => 'Ghi chú không được vượt quá 255 ký tự',

            // Thuế
      'tax_mode.integer' => 'Chế độ thuế phải là số',
      'tax_mode.in'      => 'Chế độ thuế không hợp lệ',
      'vat_rate.required_if' => 'Vui lòng nhập VAT (%) khi chọn Có thuế',
      'vat_rate.numeric'     => 'VAT (%) phải là số',
      'vat_rate.min'         => 'VAT (%) không được âm',
      'vat_rate.max'         => 'VAT (%) không vượt quá 20%',

    ];
  }
}
