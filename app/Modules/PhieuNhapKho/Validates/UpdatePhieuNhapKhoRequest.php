<?php

namespace App\Modules\PhieuNhapKho\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhieuNhapKhoRequest extends FormRequest
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
            // Thêm các quy tắc validation cho cập nhật PhieuNhapKho ở đây
            'ma_phieu_nhap_kho' => 'required|string|max:255|unique:phieu_nhap_khos,ma_phieu_nhap_kho,'.$this->id,
            'ngay_nhap_kho' => 'required|date',
            'loai_phieu_nhap' => 'required|integer',
            'nha_cung_cap_id' => 'nullable|exists:nha_cung_caps,id,trang_thai,1',
            'san_xuat_id' => 'nullable|exists:san_xuats,id,trang_thai_hoan_thanh,2',
            'so_hoa_don_nha_cung_cap' => 'nullable|string|max:255',
            'nguoi_giao_hang' => 'nullable|string|max:255',
            'so_dien_thoai_nguoi_giao_hang' => 'nullable|string|max:255',
            'thue_vat' => 'nullable|integer|min:0|max:100',
            'chi_phi_nhap_hang' => 'nullable|integer|min:0',
            'giam_gia_nhap_hang' => 'nullable|integer|min:0',
            'ghi_chu' => 'nullable|string',
            'danh_sach_san_pham' => 'required|array|min:1',
            'danh_sach_san_pham.*.san_pham_id' => 'required|exists:san_phams,id,trang_thai,1',
            'danh_sach_san_pham.*.don_vi_tinh_id' => 'required|exists:don_vi_tinhs,id,trang_thai,1',
            'danh_sach_san_pham.*.ngay_san_xuat' => 'required|date',
            'danh_sach_san_pham.*.ngay_het_han' => 'required|date|after:danh_sach_san_pham.*.ngay_san_xuat',
            'danh_sach_san_pham.*.gia_nhap' => 'required|integer|min:0',
            'danh_sach_san_pham.*.so_luong_nhap' => 'required|integer|min:0',
            'danh_sach_san_pham.*.chiet_khau' => 'nullable|integer|min:0|max:100',
            'danh_sach_san_pham.*.ghi_chu' => 'nullable|string',
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
            'ma_phieu_nhap_kho.required' => 'Mã phiếu nhập kho là bắt buộc',
            'ma_phieu_nhap_kho.max' => 'Mã phiếu nhập kho không được vượt quá 255 ký tự',
            'ma_phieu_nhap_kho.unique' => 'Mã phiếu nhập kho đã tồn tại',
            'ngay_nhap_kho.required' => 'Ngày nhập kho là bắt buộc',
            'ngay_nhap_kho.date' => 'Ngày nhập kho không hợp lệ',
            'loai_phieu_nhap.required' => 'Loại phiếu nhập là bắt buộc',
            'loai_phieu_nhap.integer' => 'Loại phiếu nhập phải là số nguyên',
            'nha_cung_cap_id.exists' => 'Nhà cung cấp không tồn tại',
            'san_xuat_id.exists' => 'Sản xuất không tồn tại',
            'so_hoa_don_nha_cung_cap.max' => 'Số hóa đơn nhà cung cấp không được vượt quá 255 ký tự',
            'nguoi_giao_hang.max' => 'Người giao hàng không được vượt quá 255 ký tự',
            'so_dien_thoai_nguoi_giao_hang.max' => 'Số điện thoại người giao hàng không được vượt quá 255 ký tự',
            'thue_vat.integer' => 'Thuế VAT phải là số nguyên',
            'thue_vat.min' => 'Thuế VAT phải lớn hơn 0',
            'thue_vat.max' => 'Thuế VAT phải nhỏ hơn 100',
            'chi_phi_nhap_hang.integer' => 'Chi phí nhập hàng phải là số nguyên',
            'chi_phi_nhap_hang.min' => 'Chi phí nhập hàng phải lớn hơn 0',
            'giam_gia_nhap_hang.integer' => 'Giảm giá nhập hàng phải là số nguyên',
            'giam_gia_nhap_hang.min' => 'Giảm giá nhập hàng phải lớn hơn 0',
            'ghi_chu.string' => 'Ghi chú phải là chuỗi',
            'danh_sach_san_pham.required' => 'Danh sách sản phẩm là bắt buộc',
            'danh_sach_san_pham.array' => 'Danh sách sản phẩm phải là mảng',
            'danh_sach_san_pham.min' => 'Danh sách sản phẩm phải có ít nhất 1 phần tử',
            'danh_sach_san_pham.*.san_pham_id.required' => 'Mã sản phẩm là bắt buộc',
            'danh_sach_san_pham.*.san_pham_id.exists' => 'Mã sản phẩm không tồn tại',
            'danh_sach_san_pham.*.nha_cung_cap_id.required' => 'Mã nhà cung cấp là bắt buộc',
            'danh_sach_san_pham.*.nha_cung_cap_id.exists' => 'Mã nhà cung cấp không tồn tại',
            'danh_sach_san_pham.*.don_vi_tinh_id.required' => 'Mã đơn vị tính là bắt buộc',
            'danh_sach_san_pham.*.don_vi_tinh_id.exists' => 'Mã đơn vị tính không tồn tại',
            'danh_sach_san_pham.*.ngay_san_xuat.required' => 'Ngày sản xuất là bắt buộc',
            'danh_sach_san_pham.*.ngay_san_xuat.date' => 'Ngày sản xuất không hợp lệ',
            'danh_sach_san_pham.*.ngay_san_xuat.before' => 'Ngày sản xuất phải trước ngày hết hạn',
            'danh_sach_san_pham.*.ngay_san_xuat.after' => 'Ngày sản xuất phải sau ngày nhập kho',
            'danh_sach_san_pham.*.ngay_het_han.required' => 'Ngày hết hạn là bắt buộc',
            'danh_sach_san_pham.*.ngay_het_han.date' => 'Ngày hết hạn không hợp lệ',
            'danh_sach_san_pham.*.ngay_het_han.after' => 'Ngày hết hạn phải sau ngày sản xuất',
            'danh_sach_san_pham.*.gia_nhap.required' => 'Giá nhập là bắt buộc',
            'danh_sach_san_pham.*.gia_nhap.integer' => 'Giá nhập phải là số nguyên',
            'danh_sach_san_pham.*.gia_nhap.min' => 'Giá nhập phải lớn hơn 0',
            'danh_sach_san_pham.*.so_luong_nhap.required' => 'Số lượng nhập là bắt buộc',
            'danh_sach_san_pham.*.so_luong_nhap.integer' => 'Số lượng nhập phải là số nguyên',
            'danh_sach_san_pham.*.so_luong_nhap.min' => 'Số lượng nhập phải lớn hơn 0',
            'danh_sach_san_pham.*.chiet_khau.integer' => 'Chiết khấu phải là số nguyên',
            'danh_sach_san_pham.*.chiet_khau.min' => 'Chiết khấu phải lớn hơn 0',
            'danh_sach_san_pham.*.chiet_khau.max' => 'Chiết khấu phải nhỏ hơn 100',
            'danh_sach_san_pham.*.ghi_chu.string' => 'Ghi chú phải là chuỗi',
        ];
    }
}
