<?php

namespace App\Modules\QuanLyTonKho;

use App\Models\KhoTong;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class QuanLyTonKhoService
{
  /**
   * Lấy tất cả dữ liệu
   * type = 1: kho tổng
   * type = 2: kho bán lẻ
   */
  public function getAll(array $params = [], $type = 1)
  {
    try {
      // Tạo query cơ bản
      if ($type == 1) {
        $query = KhoTong::query()
          ->withoutGlobalScope('withUserNames') // Tắt global scope để tránh lỗi ambiguous column khi join
          ->leftJoin('san_phams', 'kho_tongs.san_pham_id', '=', 'san_phams.id')
          ->leftJoin('chi_tiet_phieu_nhap_khos', 'kho_tongs.ma_lo_san_pham', '=', 'chi_tiet_phieu_nhap_khos.ma_lo_san_pham')
          ->leftJoin('nha_cung_caps', 'chi_tiet_phieu_nhap_khos.nha_cung_cap_id', '=', 'nha_cung_caps.id')
          ->leftJoin('don_vi_tinhs', 'chi_tiet_phieu_nhap_khos.don_vi_tinh_id', '=', 'don_vi_tinhs.id');
      } else {
        // $query = KhoBanLe::query()->with('images');
      }

      // Sử dụng FilterWithPagination để xử lý filter và pagination
      $result = FilterWithPagination::findWithPagination(
        $query,
        $params,
        $type == 1 ? [
          "kho_tongs.*",
          "san_phams.ten_san_pham",
          "nha_cung_caps.ten_nha_cung_cap",
          "don_vi_tinhs.ten_don_vi",
          "chi_tiet_phieu_nhap_khos.ngay_san_xuat",
          "chi_tiet_phieu_nhap_khos.ngay_het_han",
          DB::raw("CASE WHEN kho_tongs.so_luong_ton = 0 THEN 0 ELSE CASE WHEN kho_tongs.so_luong_ton <= san_phams.so_luong_canh_bao THEN 1 ELSE 2 END END as trang_thai"), // 0: hết hàng, 1: sắp hết hàng, 2: còn hàng
        ] : ['kho_ban_les.*'] // Columns cần select với tên bảng rõ ràng
      );

      return [
        'data' => $result['collection'],
        'total' => $result['total'],
        'pagination' => [
          'current_page' => $result['current_page'],
          'last_page' => $result['last_page'],
          'from' => $result['from'],
          'to' => $result['to'],
          'total_current' => $result['total_current']
        ]
      ];
    } catch (Exception $e) {
      throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
    }
  }

  /**
   * Lấy dữ liệu theo ID
   */
  public function getById($id)
  {
    $query = KhoTong::query()
      ->withoutGlobalScope('withUserNames') // Tắt global scope để tránh lỗi ambiguous column khi join
      ->leftJoin('san_phams', 'kho_tongs.san_pham_id', '=', 'san_phams.id')
      ->leftJoin('chi_tiet_phieu_nhap_khos', 'kho_tongs.ma_lo_san_pham', '=', 'chi_tiet_phieu_nhap_khos.ma_lo_san_pham')
      ->leftJoin('nha_cung_caps', 'chi_tiet_phieu_nhap_khos.nha_cung_cap_id', '=', 'nha_cung_caps.id')
      ->leftJoin('don_vi_tinhs', 'chi_tiet_phieu_nhap_khos.don_vi_tinh_id', '=', 'don_vi_tinhs.id')
      ->select(
        "kho_tongs.*",
        "san_phams.ten_san_pham",
        "san_phams.muc_loi_nhuan",
        "san_phams.so_luong_canh_bao",
        "nha_cung_caps.ten_nha_cung_cap",
        "don_vi_tinhs.ten_don_vi",
        "chi_tiet_phieu_nhap_khos.*",
      );

    $data = $query->find($id);
    if (!$data) {
      return CustomResponse::error('Dữ liệu không tồn tại');
    }

    return $data;
  }

  /**
   * Tạo mới dữ liệu
   */
  public function create(array $data)
  {
    try {
      $result = KhoTong::create($data);

      // TODO: Thêm ảnh vào bảng images (nếu có)
      // $result->images()->create([
      //   'path' => $data['image'],
      // ]);

      return $result;
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  /**
   * Cập nhật dữ liệu
   */
  public function update($id, array $data)
  {
    try {
      $model = KhoTong::findOrFail($id);
      $model->update($data);

      // TODO: Cập nhật ảnh vào bảng images (nếu có)
      // if ($data['image']) {
      //   $model->images()->get()->each(function ($image) use ($data) {
      //     $image->update([
      //       'path' => $data['image'],
      //     ]);
      //   });
      // }


      return $model->fresh();
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }


  /**
   * Xóa dữ liệu
   */
  public function delete($id)
  {
    try {
      $model = KhoTong::findOrFail($id);

      // TODO: Xóa ảnh vào bảng images (nếu có)
      // $model->images()->get()->each(function ($image) {
      //   $image->delete();
      // });

      return $model->delete();
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  /**
   * Lấy danh sách QuanLyTonKho dạng option
   */
  public function getOptions()
  {
    return KhoTong::select('id as value', 'ten_quan_ly_ton_kho as label')->get();
  }
}