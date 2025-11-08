<?php

namespace App\Modules\QuanLyBanHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateQuanLyBanHangRequest extends FormRequest
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
      // ===== Thông tin đơn hàng =====
      // BE sẽ tự sinh => KHÔNG required; vẫn đảm bảo không trùng nếu có truyền vào
      'ma_don_hang'        => 'sometimes|nullable|string|max:255|unique:don_hangs,ma_don_hang',
      'ngay_tao_don_hang'  => 'required|date',
      'dia_chi_giao_hang'  => 'required|string',

      // ===== TRẠNG THÁI ĐƠN HÀNG (NEW) =====
      // 0 = Chưa giao, 1 = Đang giao, 2 = Đã giao, 3 = Đã hủy
      'trang_thai_don_hang' => ['sometimes','nullable','integer', Rule::in([0,1,2,3])],

      // ===== Thông tin người nhận (BỔ SUNG) =====
      'nguoi_nhan_ten'        => ['nullable', 'string', 'max:191'],
      'nguoi_nhan_sdt'        => ['nullable', 'string', 'max:20', 'regex:/^(0|\+84)\d{8,12}$/'],
    'nguoi_nhan_thoi_gian'  => ['required', 'date'],


      // ===== Khách hàng =====
      // 0 = KH hệ thống, 1 = KH tự do
      'loai_khach_hang'    => ['required', 'integer', Rule::in([0, 1])],
      'khach_hang_id'      => ['nullable', 'integer', 'exists:khach_hangs,id', 'required_if:loai_khach_hang,0'],
      'ten_khach_hang'     => ['nullable', 'string', 'max:255', 'required_if:loai_khach_hang,1'],
      'so_dien_thoai'      => ['nullable', 'string', 'max:255', 'required_if:loai_khach_hang,1'],

      // ===== Chi phí – giảm trừ (đơn giá đã gồm VAT, không dùng VAT nữa) =====
      'giam_gia'           => ['required', 'numeric', 'min:0'],
      'chi_phi'            => ['required', 'numeric', 'min:0'],

      // ===== Thanh toán =====
      // 0 = Chưa thanh toán, 1 = Thanh toán một phần, 2 = Thanh toán toàn bộ
      'loai_thanh_toan'        => ['required', 'integer', Rule::in([0, 1, 2])],
      // Chỉ yêu cầu khi "Thanh toán một phần"; còn lại service sẽ ép về 0 hoặc = tổng
      'so_tien_da_thanh_toan'  => [
        'nullable',
        'numeric',
        'min:0',
        Rule::requiredIf(fn () => (int)$this->input('loai_thanh_toan') === 1),
      ],

      // ===== Thuế (NEW) =====
      'tax_mode'  => ['sometimes', 'integer', Rule::in([0, 1])],   // 0 = không thuế (mặc định), 1 = VAT
      'vat_rate'  => [
        'sometimes', 'nullable', 'numeric', 'min:0', 'max:20',
        Rule::requiredIf(fn () => (int)$this->input('tax_mode') === 1),
      ],



      // ===== Danh sách sản phẩm =====
      'danh_sach_san_pham'                  => ['required', 'array', 'min:1'],
      'danh_sach_san_pham.*.san_pham_id'    => ['required', 'integer', 'exists:san_phams,id'],
      'danh_sach_san_pham.*.don_vi_tinh_id' => ['required', 'integer', 'exists:don_vi_tinhs,id'],
      'danh_sach_san_pham.*.so_luong'       => ['required', 'numeric', 'min:1'],
      // loai_gia: 1 = Đặt ngay, 2 = Đặt trước 3 ngày (service vẫn default = 1 nếu thiếu)
      'danh_sach_san_pham.*.loai_gia'       => ['required', 'integer', Rule::in([1, 2])],
      // don_gia/thanh_tien cho phép truyền lên để hiển thị, service sẽ tính lại
      'danh_sach_san_pham.*.don_gia'        => ['nullable', 'numeric', 'min:0'],
      'danh_sach_san_pham.*.thanh_tien'     => ['nullable', 'numeric', 'min:0'],

      // ===== Khác =====
      'ghi_chu'             => ['nullable', 'string', 'max:255'],
      'images'              => ['nullable', 'array'],
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
      // (Không còn required cho ma_don_hang)
      'ma_don_hang.unique'         => 'Mã đơn hàng đã tồn tại',
      'ma_don_hang.max'            => 'Mã đơn hàng không được vượt quá 255 ký tự',
      'ngay_tao_don_hang.required' => 'Ngày tạo đơn hàng là bắt buộc',
      'ngay_tao_don_hang.date'     => 'Ngày tạo đơn hàng không hợp lệ',
      'dia_chi_giao_hang.required' => 'Địa chỉ giao hàng là bắt buộc',

      // Trạng thái (NEW)
      'trang_thai_don_hang.integer' => 'Trạng thái đơn hàng phải là số',
      'trang_thai_don_hang.in'      => 'Trạng thái đơn hàng phải là 0, 1, 2 hoặc 3',

      // Thông tin người nhận (BỔ SUNG)
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
      'khach_hang_id.required_if'  => 'Vui lòng chọn khách hàng hệ thống',
      'khach_hang_id.integer'      => 'Khách hàng phải là số',
      'khach_hang_id.exists'       => 'Khách hàng không tồn tại',
      'ten_khach_hang.required_if' => 'Tên khách hàng không được bỏ trống (khách hàng tự do)',
      'ten_khach_hang.string'      => 'Tên khách hàng phải là chuỗi',
      'ten_khach_hang.max'         => 'Tên khách hàng không được vượt quá 255 ký tự',
      'so_dien_thoai.required_if'  => 'Số điện thoại không được bỏ trống (khách hàng tự do)',
      'so_dien_thoai.string'       => 'Số điện thoại phải là chuỗi',
      'so_dien_thoai.max'          => 'Số điện thoại không được vượt quá 255 ký tự',

      // Giảm trừ/Chi phí
      'giam_gia.required' => 'Giảm giá là bắt buộc',
      'giam_gia.numeric'  => 'Giảm giá phải là số',
      'giam_gia.min'      => 'Giảm giá không được âm',
      'chi_phi.required'  => 'Chi phí là bắt buộc',
      'chi_phi.numeric'   => 'Chi phí phải là số',
      'chi_phi.min'       => 'Chi phí không được âm',

      // Thanh toán
      'loai_thanh_toan.required' => 'Loại thanh toán là bắt buộc',
      'loai_thanh_toan.integer'  => 'Loại thanh toán phải là số',
      'loai_thanh_toan.in'       => 'Loại thanh toán phải là 0, 1 hoặc 2',
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
