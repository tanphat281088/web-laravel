<?php

namespace App\Modules\SanPham\Validates;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\DonViTinh;
use App\Models\NhaCungCap;
use Illuminate\Validation\Validator;

class CreateSanPhamRequest extends FormRequest
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
      // Thêm các quy tắc validation cho SanPham ở đây
      'ma_san_pham'        => 'required|string|max:255|unique:san_phams,ma_san_pham',
      'ten_san_pham'       => 'required|string|max:255',
      'image'              => 'nullable|string',
      'danh_muc_id'        => 'required|integer|exists:danh_muc_san_phams,id',
      'don_vi_tinh_id'     => 'required|array',
      'nha_cung_cap_id'    => 'nullable|array',

      // Giá
      'gia_nhap_mac_dinh'  => 'required|numeric',
      'gia_dat_truoc_3n'   => 'nullable|numeric|min:0', // ✅ MỚI

      // Tỷ lệ & cảnh báo
      'ty_le_chiet_khau'   => 'nullable|numeric|min:0|max:100',
      'muc_loi_nhuan'      => 'nullable|numeric|min:0|max:100',
      'so_luong_canh_bao'  => 'required|numeric|min:0',

      'ghi_chu'            => 'nullable|string',
      'trang_thai'         => 'required|integer|in:0,1',
      'loai_san_pham'      => 'required|string|in:SP_NHA_CUNG_CAP,SP_SAN_XUAT,NGUYEN_LIEU',
    ];
  }

  /**
   * Configure the validator instance.
   *
   * @param \Illuminate\Validation\Validator $validator
   * @return void
   */
  public function withValidator(Validator $validator)
  {
    $validator->after(function ($validator) {
      // Validate đơn vị tính
      $donViTinhId = $this->don_vi_tinh_id;
      if (!empty($donViTinhId)) {
        // Chuyển đổi tất cả các phần tử trong mảng ID thành số nguyên
        $donViTinhIds = array_map('intval', $donViTinhId);
        $existingDonViTinhIds = DonViTinh::whereIn('id', $donViTinhIds)->pluck('id')->toArray();
        // Đảm bảo mảng kết quả cũng là số nguyên
        $existingDonViTinhIds = array_map('intval', $existingDonViTinhIds);
        $missingDonViTinhIds = array_diff($donViTinhIds, $existingDonViTinhIds);

        if (!empty($missingDonViTinhIds)) {
          $validator->errors()->add('don_vi_tinh_id', 'Một số id đơn vị tính không tồn tại: ' . implode(', ', $missingDonViTinhIds));
        }
      }

      // Validate nhà cung cấp (chỉ khi loại sản phẩm là SP_NHA_CUNG_CAP hoặc NGUYEN_LIEU)
      $loaiSanPham = $this->loai_san_pham;
      $nhaCungCapId = $this->nha_cung_cap_id;
      if (in_array($loaiSanPham, ['SP_NHA_CUNG_CAP', 'NGUYEN_LIEU']) && !empty($nhaCungCapId)) {
        // Chuyển đổi tất cả các phần tử trong mảng ID thành số nguyên
        $nhaCungCapIds = array_map('intval', $nhaCungCapId);
        $existingNhaCungCapIds = NhaCungCap::whereIn('id', $nhaCungCapIds)->pluck('id')->toArray();
        // Đảm bảo mảng kết quả cũng là số nguyên
        $existingNhaCungCapIds = array_map('intval', $existingNhaCungCapIds);
        $missingNhaCungCapIds = array_diff($nhaCungCapIds, $existingNhaCungCapIds);

        if (!empty($missingNhaCungCapIds)) {
          $validator->errors()->add('nha_cung_cap_id', 'Một số id nhà cung cấp không tồn tại: ' . implode(', ', $missingNhaCungCapIds));
        }
      }
    });
  }

  /**
   * Get the error messages for the defined validation rules.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'ma_san_pham.required' => 'Mã sản phẩm là bắt buộc',
      'ma_san_pham.max' => 'Mã sản phẩm không được vượt quá 255 ký tự',
      'ten_san_pham.required' => 'Tên sản phẩm là bắt buộc',
      'ten_san_pham.max' => 'Tên sản phẩm không được vượt quá 255 ký tự',
      'danh_muc_id.required' => 'Danh mục sản phẩm là bắt buộc',
      'danh_muc_id.integer' => 'Danh mục sản phẩm phải là số nguyên',
      'danh_muc_id.exists' => 'Danh mục sản phẩm không tồn tại',
      'don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'don_vi_tinh_id.array' => 'Đơn vị tính phải là mảng',
      'don_vi_tinh_id.min' => 'Đơn vị tính phải có ít nhất 1 phần tử',
      'nha_cung_cap_id.array' => 'Nhà cung cấp phải là mảng',
      'nha_cung_cap_id.min' => 'Nhà cung cấp phải có ít nhất 1 phần tử',

      'gia_nhap_mac_dinh.required' => 'Giá nhập mặc định là bắt buộc',
      'gia_nhap_mac_dinh.numeric' => 'Giá nhập mặc định phải là số',

      // ✅ MỚI:
      'gia_dat_truoc_3n.numeric' => 'Giá đặt trước 3 ngày phải là số',
      'gia_dat_truoc_3n.min'     => 'Giá đặt trước 3 ngày phải ≥ 0',

      'ty_le_chiet_khau.numeric' => 'Tỷ lệ chiết khấu phải là số',
      'ty_le_chiet_khau.min' => 'Tỷ lệ chiết khấu phải lớn hơn 0',
      'ty_le_chiet_khau.max' => 'Tỷ lệ chiết khấu phải nhỏ hơn 100',
      'muc_loi_nhuan.numeric' => 'Mức lợi nhuận phải là số',
      'muc_loi_nhuan.min' => 'Mức lợi nhuận phải lớn hơn 0',
      'muc_loi_nhuan.max' => 'Mức lợi nhuận phải nhỏ hơn 100',
      'so_luong_canh_bao.required' => 'Số lượng cảnh báo là bắt buộc',
      'so_luong_canh_bao.numeric' => 'Số lượng cảnh báo phải là số',
      'so_luong_canh_bao.min' => 'Số lượng cảnh báo phải lớn hơn 0',
      'trang_thai.required' => 'Trạng thái là bắt buộc',
      'trang_thai.integer' => 'Trạng thái phải là số nguyên',
      'trang_thai.in' => 'Trạng thái phải là 0 hoặc 1',
      'loai_san_pham.required' => 'Loại sản phẩm là bắt buộc',
      'loai_san_pham.string' => 'Loại sản phẩm phải là chuỗi',
      'loai_san_pham.in' => 'Loại sản phẩm phải là SP_NHA_CUNG_CAP, SP_SAN_XUAT hoặc NGUYEN_LIEU',
    ];
  }
}
