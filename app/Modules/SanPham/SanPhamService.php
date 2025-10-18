<?php

namespace App\Modules\SanPham;

use App\Models\SanPham;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\KhoTong;

class SanPhamService
{
  /**
   * Lấy tất cả dữ liệu (đã fix tổng & phân trang an toàn với GROUP BY)
   */
  public function getAll(array $params = [])
  {
    try {
      // Query cơ bản với JOIN (giữ nguyên logic cũ)
      $query = SanPham::query()
        ->withoutGlobalScopes(['withUserNames'])
        ->with('images', 'danhMuc:id,ten_danh_muc')
        ->leftJoin('users as nguoi_tao', 'san_phams.nguoi_tao', '=', 'nguoi_tao.id')
        ->leftJoin('users as nguoi_cap_nhat', 'san_phams.nguoi_cap_nhat', '=', 'nguoi_cap_nhat.id')
        ->leftJoin('chi_tiet_phieu_nhap_khos', 'san_phams.id', '=', 'chi_tiet_phieu_nhap_khos.san_pham_id')
        ->leftJoin('kho_tongs', 'san_phams.id', '=', 'kho_tongs.san_pham_id')
        // join bảng master để lấy tên hiển thị loại sản phẩm
        ->leftJoin('loai_san_pham_masters as lsp', 'lsp.code', '=', 'san_phams.loai_san_pham')
        ->groupBy('san_phams.id');

      // Đọc tham số phân trang từ FE (hỗ trợ cả limit và per_page)
      $page    = max(1, (int)($params['page']      ?? 1));
      $perPage = max(1, (int)($params['limit']     ?? ($params['per_page'] ?? 20)));

      // ===== (1) Tính tổng bản ghi thật sự =====
      // IMPORTANT: Khi đếm tổng, cần loại bỏ GROUP BY/ORDER BY khỏi bản sao
      $countQuery = (clone $query);
      $base = $countQuery->getQuery();   // Query\Builder bên dưới
      $base->groups = null;              // bỏ GROUP BY
      $base->orders = null;              // tránh lỗi "ORDER BY without GROUP BY" khi COUNT
      $total = (int) $countQuery
        ->select(DB::raw('COUNT(DISTINCT san_phams.id) as agg_total'))
        ->value('agg_total');

      // ===== (2) Lấy dữ liệu trang hiện tại =====
      $collection = $query
        ->select([
          'san_phams.*',
          // tên người tạo/cập nhật
          'nguoi_tao.name as ten_nguoi_tao',
          'nguoi_cap_nhat.name as ten_nguoi_cap_nhat',
          // tổng số lượng
          DB::raw('COALESCE(SUM(chi_tiet_phieu_nhap_khos.so_luong_nhap), 0) as tong_so_luong_nhap'),
          DB::raw('COALESCE(SUM(kho_tongs.so_luong_ton), 0) as tong_so_luong_thuc_te'),
          // tên loại hiển thị
          DB::raw('COALESCE(lsp.ten_hien_thi, san_phams.loai_san_pham) as ten_loai'),
        ])
        ->forPage($page, $perPage)
        ->get();

      // ===== (3) Trả về đúng cấu trúc cũ để FE không cần thay đổi =====
      return [
        'data' => $collection,
        'total' => $total,
        'pagination' => [
          'current_page'  => $page,
          'last_page'     => max(1, (int) ceil($total / $perPage)),
          'from'          => ($total === 0) ? 0 : (($page - 1) * $perPage + 1),
          'to'            => min($page * $perPage, $total),
          'total_current' => $collection->count(),
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
    $data = SanPham::with([
      'images',
      'donViTinhs' => function ($query) {
        $query->withoutGlobalScope('withUserNames')->select('don_vi_tinhs.id as value', 'ten_don_vi as label');
      },
      'nhaCungCaps' => function ($query) {
        $query->withoutGlobalScope('withUserNames')->select('nha_cung_caps.id as value', 'ten_nha_cung_cap as label');
      },
      'danhMuc'
    ])->find($id);

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
      $result = SanPham::create([
        'ma_san_pham'        => $data['ma_san_pham'],
        'ten_san_pham'       => $data['ten_san_pham'],
        'danh_muc_id'        => $data['danh_muc_id'],
        'gia_nhap_mac_dinh'  => $data['gia_nhap_mac_dinh'],
        'gia_dat_truoc_3n'   => $data['gia_dat_truoc_3n'] ?? 0, // MỚI
        'ty_le_chiet_khau'   => $data['ty_le_chiet_khau'],
        'muc_loi_nhuan'      => $data['muc_loi_nhuan'],
        'so_luong_canh_bao'  => $data['so_luong_canh_bao'],
        'loai_san_pham'      => $data['loai_san_pham'], // lưu CODE ổn định
        'ghi_chu'            => $data['ghi_chu'] ?? null,
        'trang_thai'         => $data['trang_thai'],
      ]);

      if (isset($data['don_vi_tinh_id'])) {
        $result->donViTinhs()->attach($data['don_vi_tinh_id'], [
          'nguoi_tao' => Auth::user()->id,
          'nguoi_cap_nhat' => Auth::user()->id
        ]);
      }

      if (isset($data['nha_cung_cap_id'])) {
        $result->nhaCungCaps()->attach($data['nha_cung_cap_id'], [
          'nguoi_tao' => Auth::user()->id,
          'nguoi_cap_nhat' => Auth::user()->id
        ]);
      }

      // Thêm ảnh nếu có
      if (!empty($data['image'])) {
        $result->images()->create(['path' => $data['image']]);
      }

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
      $model = SanPham::findOrFail($id);

      $sanPhamData = $data;
      unset($sanPhamData['don_vi_tinh_id'], $sanPhamData['nha_cung_cap_id']);

      $model->update($sanPhamData);

      // Cập nhật ảnh nếu có
      if (!empty($data['image'])) {
        $model->images()->get()->each(function ($image) use ($data) {
          $image->update(['path' => $data['image']]);
        });
      }

      if (isset($data['don_vi_tinh_id'])) {
        $ids = is_array($data['don_vi_tinh_id']) ? $data['don_vi_tinh_id'] : [$data['don_vi_tinh_id']];
        $ids = array_map('intval', $ids);

        // build pivot data: id => ['nguoi_tao' => ..., 'nguoi_cap_nhat' => ...]
        $pivot = [];
        foreach ($ids as $idDvt) {
          $pivot[$idDvt] = [
            'nguoi_tao'      => Auth::id(),
            'nguoi_cap_nhat' => Auth::id(),
          ];
        }

        // dùng sync với pivot values
        $model->donViTinhs()->sync($pivot);
      }

      if (isset($data['nha_cung_cap_id'])) {
        $ids = is_array($data['nha_cung_cap_id']) ? $data['nha_cung_cap_id'] : [$data['nha_cung_cap_id']];
        $ids = array_map('intval', $ids);

        $pivot = [];
        foreach ($ids as $idNcc) {
          $pivot[$idNcc] = [
            'nguoi_tao'      => Auth::id(),
            'nguoi_cap_nhat' => Auth::id(),
          ];
        }

        $model->nhaCungCaps()->sync($pivot);
      }

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
      $model = SanPham::findOrFail($id);

      // Xóa ảnh nếu có
      $model->images()->get()->each(function ($image) {
        $image->delete();
      });

      $model->donViTinhs()->detach();
      $model->nhaCungCaps()->detach();

      return $model->delete();
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  /**
   * Lấy danh sách SanPham dạng option
   * - label: Tên sản phẩm (Tên loại hiển thị)
   */
  /**
   * Lấy danh sách SanPham dạng option cho combobox (tạo đơn hàng)
   * - Hỗ trợ tìm theo MÃ SP (ưu tiên) và theo TÊN (không phân biệt hoa/thường)
   * - Tham số hỗ trợ: search | q | keyword
   * - Trả về: [{ value: id, label: "Tên SP - <MÃ> (Tên loại)" }, ...]
   * - Giới hạn 50 dòng để dropdown nhẹ
   */
  /**
 * Lấy danh sách SanPham dạng option cho combobox (tạo đơn hàng)
 * - Tìm theo MÃ (ưu tiên) & theo TÊN
 * - Tham số hỗ trợ: search | q | keyword
 * - Không lặp mã ở cuối label nếu tên đã chứa mã
 * - Giới hạn 50 dòng
 */
/**
 * Lấy danh sách SanPham cho combobox (tạo đơn hàng)
 * - Khi người dùng gõ SỐ: chỉ tìm theo MÃ (5 số, nhưng hỗ trợ gõ một phần: =, prefix, contains)
 * - Khi gõ CHỮ: tìm theo TÊN (không phân biệt hoa/thường)
 * - Trả về: [{ value: id, label: "Tên SP - MÃ" }]
 * - Giới hạn 50 dòng
 */
public function getOptions(array $params = [])
{
    $qRaw = trim((string)($params['search'] ?? $params['q'] ?? $params['keyword'] ?? ''));
    $q    = mb_strtolower($qRaw, 'UTF-8');
    $digits = preg_replace('/\D+/', '', $qRaw); // lấy phần số người dùng nhập

    $query = SanPham::query()->withoutGlobalScopes(['withUserNames']);

    if ($q !== '') {
        $isNumeric = ($digits !== '') && ctype_digit($digits);

        if ($isNumeric) {
            // 👉 Chỉ tìm theo MÃ
            $query->where(function ($x) use ($digits) {
                $x->where('san_phams.ma_san_pham', '=', $digits)          // khớp tuyệt đối
                  ->orWhere('san_phams.ma_san_pham', 'like', $digits.'%') // bắt đầu bằng
                  ->orWhere('san_phams.ma_san_pham', 'like', '%'.$digits.'%'); // chứa
            });

            // Ưu tiên: =  → prefix → contains
            $query->orderByRaw('CASE 
                WHEN san_phams.ma_san_pham = ? THEN 0
                WHEN san_phams.ma_san_pham LIKE ? THEN 1
                ELSE 2 END', [$digits, $digits.'%'])
                  ->orderBy('san_phams.ma_san_pham');
        } else {
            // 👉 Tìm theo tên (không phân biệt hoa/thường)
            $query->whereRaw('LOWER(san_phams.ten_san_pham) LIKE ?', ['%'.$q.'%'])
                  ->orderBy('san_phams.ten_san_pham');
        }
    } else {
        $query->orderBy('san_phams.ten_san_pham');
    }

    $rows = $query->select([
            'san_phams.id',
            'san_phams.ma_san_pham',
            'san_phams.ten_san_pham',
        ])
        ->limit(50)
        ->get();

    // Label: "Tên SP - MÃ" (tránh lặp mã nếu tên đã có mã sẵn)
    return $rows->map(function ($r) {
        $name = (string)$r->ten_san_pham;
        $code = (string)$r->ma_san_pham;
        $hasCodeInName = (bool)preg_match('/(^|[^0-9])'.preg_quote($code,'/').'($|[^0-9])/u', $name);
        $label = $hasCodeInName ? $name : "{$name} - {$code}";
        return ['value' => (int)$r->id, 'label' => $label];
    });
}


  /**
   * Lấy danh sách SanPham dạng option theo NhaCungCap
   * - loại bỏ SP_SX khi lọc NCC
   * - label: Tên sản phẩm (Tên loại hiển thị)
   */
  public function getOptionsByNhaCungCap($nhaCungCapId)
  {
    return SanPham::whereHas('nhaCungCaps', function ($query) use ($nhaCungCapId) {
        $query->withoutGlobalScope('withUserNames')
          ->where('nha_cung_caps.id', $nhaCungCapId)
          ->where('san_phams.loai_san_pham', '!=', 'SP_SAN_XUAT');
      })
      ->withoutGlobalScope('withUserNames')
      ->leftJoin('loai_san_pham_masters as lsp', 'lsp.code', '=', 'san_phams.loai_san_pham')
      ->select(
        'san_phams.id as value',
        DB::raw('CONCAT(san_phams.ten_san_pham, " (", COALESCE(lsp.ten_hien_thi, san_phams.loai_san_pham), ")") as label')
      )
      ->get();
  }

  /**
   * Lấy danh sách Lô Sản Phẩm dạng option theo Sản phẩm
   */
  public function getOptionsLoSanPhamBySanPhamIdAndDonViTinhId($sanPhamId, $donViTinhId)
  {
    $loSanPham = KhoTong::where('kho_tongs.san_pham_id', $sanPhamId)
      ->where('kho_tongs.don_vi_tinh_id', $donViTinhId)
      ->leftJoin('chi_tiet_phieu_nhap_khos', 'kho_tongs.ma_lo_san_pham', '=', 'chi_tiet_phieu_nhap_khos.ma_lo_san_pham')
      ->withoutGlobalScope('withUserNames')
      ->select(
        'kho_tongs.ma_lo_san_pham as value',
        DB::raw('CONCAT(kho_tongs.ma_lo_san_pham, " - NSX: ", DATE_FORMAT(chi_tiet_phieu_nhap_khos.ngay_san_xuat, "%d/%m/%Y"), " - HSD: ", DATE_FORMAT(chi_tiet_phieu_nhap_khos.ngay_het_han, "%d/%m/%Y"), " - SL Tồn: ", kho_tongs.so_luong_ton, " - HSD Còn lại: ", DATEDIFF(chi_tiet_phieu_nhap_khos.ngay_het_han, CURDATE()), " ngày") as label'),
        DB::raw('DATEDIFF(chi_tiet_phieu_nhap_khos.ngay_het_han, CURDATE()) as hsd_con_lai')
      )
      ->orderBy('hsd_con_lai', 'asc')
      ->get();

    return $loSanPham;
  }
}
