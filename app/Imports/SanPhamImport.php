<?php

namespace App\Imports;

use App\Models\SanPham;
use App\Models\DonViTinh;
use App\Models\NhaCungCap;
use App\Modules\SanPham\Validates\CreateSanPhamRequest;
use App\Modules\SanPham\Validates\UpdateSanPhamRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\LichSuImport;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Exception;

class SanPhamImport implements ToCollection, WithMultipleSheets
{
  // ===== Cấu hình / thống kê =====
  protected string $muc_import = 'Sản phẩm';
  protected string $model_class = SanPham::class;
  protected string $createRequest = CreateSanPhamRequest::class;
  protected string $updateRequest = UpdateSanPhamRequest::class;

  protected int $thanh_cong = 0;
  protected int $that_bai = 0;
  protected int $created = 0;
  protected int $updated = 0;
  protected array $ket_qua_import = [];
  protected array $validated_rows = [];
  protected int $tong_so_luong = 0;


  // ===== Auto-map defaults =====
protected ?int $DM_DEFAULT_ID  = null;
protected ?int $DVT_DEFAULT_ID = null;

/** Lấy/khởi tạo ID danh mục mặc định (DM_DEFAULT / 'Chưa phân loại') */
protected function ensureDefaultDanhMuc(): int {
  if ($this->DM_DEFAULT_ID !== null) return $this->DM_DEFAULT_ID;
  $id = \DB::table('danh_muc_san_phams')
        ->where('ma_danh_muc', 'DM_DEFAULT')
        ->orWhere('ten_danh_muc', 'Chưa phân loại')
        ->value('id');
  if (!$id) {
    $id = \DB::table('danh_muc_san_phams')->insertGetId([
      'ma_danh_muc'    => 'DM_DEFAULT',
      'ten_danh_muc'   => 'Chưa phân loại',
      'trang_thai'     => 1,
      'nguoi_tao'      => 'import',
      'nguoi_cap_nhat' => 'import',
      'created_at'     => now(),
      'updated_at'     => now(),
    ]);
  }
  return $this->DM_DEFAULT_ID = (int)$id;
}

/**
 * Lấy/khởi tạo ID đơn vị tính mặc định (DVT_DEFAULT)
 * Lưu ý: Bảng don_vi_tinhs của bạn không có cột ma_don_vi,
 * nên dùng ten_don_vi = 'ĐVT mặc định' làm mốc.
 */
protected function ensureDefaultDonViTinh(): int
{
    if ($this->DVT_DEFAULT_ID !== null) {
        return $this->DVT_DEFAULT_ID;
    }

    // Tra theo tên vì không có cột ma_don_vi
    $id = \DB::table('don_vi_tinhs')
        ->where('ten_don_vi', 'ĐVT mặc định')
        ->value('id');

    if (!$id) {
        $id = \DB::table('don_vi_tinhs')->insertGetId([
            'ten_don_vi'     => 'ĐVT mặc định',
            'trang_thai'     => 1,
            'nguoi_tao'      => 'import',
            'nguoi_cap_nhat' => 'import',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    return $this->DVT_DEFAULT_ID = (int) $id;
}


/** Valid code cho loại sản phẩm */
protected function normalizeLoaiSanPham($v): string {
  $ok = ['SP_NHA_CUNG_CAP','SP_SAN_XUAT','NGUYEN_LIEU'];
  $v  = trim((string)$v);
  return in_array($v, $ok, true) ? $v : 'SP_SAN_XUAT';
}

/** Rút mã số ở đuôi tên nếu có (… - 31587) */
protected function extractCodeFromName(?string $name): ?string {
  if (!$name) return null;
  if (preg_match('/(?:-|\\s)(\\d{3,})\\s*$/u', $name, $m)) return $m[1];
  return null;
}

/** Kiểm tra danh_muc_id có tồn tại không */
protected function danhMucExists(?int $id): bool {
  if (!$id) return false;
  return \DB::table('danh_muc_san_phams')->where('id',$id)->exists();
}


  public function sheets(): array
  {
    // Chỉ đọc sheet đầu tiên (index 0)
    return [ 0 => $this ];
  }

  /**
   * Điểm vào import: nhận toàn bộ collection của sheet 0
   */
  public function collection(Collection $collection)
  {
    // Bỏ qua dòng header
    $rows = array_slice($collection->toArray(), 1);

    // Chuẩn bị defaults (danh mục & ĐVT)
$this->ensureDefaultDanhMuc();
$this->ensureDefaultDonViTinh();

    $this->tong_so_luong = count($rows);

    // 1) Validate tất cả dòng (không chặn cả lô)
    $this->validateAllRows($rows);

    // 2) Ghi dữ liệu theo từng dòng đã validate (per-row, không ảnh hưởng lẫn nhau)
    $this->upsertValidatedRows();

    // 3) Lưu lịch sử import
    $this->saveLichSuImport();
  }

  /**
   * Chuẩn hoá giá trị số nguyên
   */
  protected function toInt($v): int
  {
    if ($v === '' || $v === null) return 0;
    return (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
  }

  /**
   * Chuẩn hoá giá trị số thực (tỷ lệ %)
   */
  protected function toFloat($v): float
  {
    if ($v === '' || $v === null) return 0.0;
    // cho phép số thập phân dạng "12,5" → 12.5
    $v = is_string($v) ? str_replace(',', '.', $v) : $v;
    return (float) $v;
  }

  /**
   * Tách danh sách ID từ chuỗi "1,2, 3"
   */
  protected function splitIds($val): array
  {
    if ($val === null || $val === '') return [];
    if (is_array($val)) return array_values(array_filter(array_map('intval', $val)));
    $parts = array_map('trim', explode(',', (string) $val));
    $ids = array_map('intval', array_filter($parts, fn($x) => $x !== '' && is_numeric($x)));
    return array_values(array_unique($ids));
  }

  /**
   * Validate tất cả dữ liệu (không tạo khi còn dòng lỗi; nhưng vẫn cho phép mix create/update)
   * Mỗi dòng sau validate sẽ push vào $this->validated_rows với metadata để tạo/update ở bước sau
   */
  protected function validateAllRows(array $rows): void
  {
    foreach ($rows as $index => $item) {
      try {
        // Map cột Excel → field
        // [0]=image, [1]=ma_san_pham, [2]=ten_san_pham, [3]=danh_muc_id,
        // [4]=loai_san_pham, [5]=don_vi_tinh_id CSV, [6]=nha_cung_cap_id CSV,
        // [7]=gia_nhap_mac_dinh, [8]=gia_dat_truoc_3n, [9]=ty_le_chiet_khau,
        // [10]=muc_loi_nhuan, [11]=so_luong_canh_bao, [12]=trang_thai, [13]=ghi_chu
        $raw = $item;

        $donViTinhIds = $this->splitIds($raw[5] ?? null);
        $nhaCungCapIds = $this->splitIds($raw[6] ?? null);

        $rowData = [
          'image'              => $raw[0] ?? null,
          'ma_san_pham'        => isset($raw[1]) ? trim((string)$raw[1]) : null,
          'ten_san_pham'       => isset($raw[2]) ? trim((string)$raw[2]) : null,
          'danh_muc_id'        => ($raw[3] === '' || $raw[3] === null) ? null : (int)$raw[3],
          'loai_san_pham'      => isset($raw[4]) ? trim((string)$raw[4]) : null,
          'don_vi_tinh_id'     => $donViTinhIds,
          'nha_cung_cap_id'    => $nhaCungCapIds,
          'gia_nhap_mac_dinh'  => $this->toInt($raw[7] ?? 0),
          'gia_dat_truoc_3n'   => $this->toInt($raw[8] ?? 0),
          'ty_le_chiet_khau'   => $this->toFloat($raw[9] ?? 0),
          'muc_loi_nhuan'      => $this->toFloat($raw[10] ?? 0),
          'so_luong_canh_bao'  => $this->toInt($raw[11] ?? 0),
          'trang_thai'         => isset($raw[12]) && $raw[12] !== '' ? (int)$raw[12] : 1,
          'ghi_chu'            => $raw[13] ?? null,
        ];


// === AUTO-MAP BẮT BUỘC & DỌN DÒNG TRẮNG ===

// 3.1 Skip hoàn toàn hàng trắng: thiếu cả mã & tên
if (empty($rowData['ma_san_pham']) && empty($rowData['ten_san_pham'])) {
  // không tính lỗi — chỉ bỏ qua
  continue;
}

// 3.2 Nếu thiếu mã, thử rút số ở đuôi tên; nếu vẫn thiếu → skip
if (empty($rowData['ma_san_pham'])) {
  $guess = $this->extractCodeFromName($rowData['ten_san_pham'] ?? null);
  if ($guess) {
    $rowData['ma_san_pham'] = $guess;
  } else {
    $this->addFailedResult($index, $raw, 'Thiếu mã & không thể suy mã từ tên', []);
    continue;
  }
}

// 3.3 Chuẩn hóa loại sản phẩm
$rowData['loai_san_pham'] = $this->normalizeLoaiSanPham($rowData['loai_san_pham'] ?? '');

// 3.4 ĐVT mặc định nếu thiếu
if (empty($rowData['don_vi_tinh_id'])) {
  $rowData['don_vi_tinh_id'] = [$this->ensureDefaultDonViTinh()];
}

// 3.5 CREATE: map danh_muc_id rỗng/không hợp lệ → DM_DEFAULT
// (UPDATE: không ép danh_muc_id — giữ nguyên theo yêu cầu)


        // Kiểm tra tồn tại theo ma_san_pham
        $existing = null;
        if (!empty($rowData['ma_san_pham'])) {
          $existing = SanPham::query()->where('ma_san_pham', $rowData['ma_san_pham'])->first();
        }

        // Chọn FormRequest phù hợp
        if ($existing) {
          // ---- UPDATE ----
          $request = new $this->updateRequest();
          // Tiêm ID để rule unique hoạt động đúng
          $request->merge(array_merge($rowData, ['id' => $existing->id]));
          $rules = $request->rules();
          $messages = method_exists($request, 'messages') ? $request->messages() : [];
        } else {
          // ---- CREATE ----
          
    if (!$this->danhMucExists($rowData['danh_muc_id'] ?? null)) {
  $rowData['danh_muc_id'] = $this->ensureDefaultDanhMuc();
}
      

          $request = new $this->createRequest();
          $request->merge($rowData);
          $rules = $request->rules();
          $messages = method_exists($request, 'messages') ? $request->messages() : [];
        }

        // Validate chính
        $validator = Validator::make($rowData, $rules, $messages);

        // withValidator callbacks (kiểm tra tồn tại ĐVT/NCC…)
        if (method_exists($request, 'withValidator')) {
          $request->withValidator($validator);
        }

        if ($validator->fails()) {
          $this->addFailedResult($index, $raw, 'Lỗi dữ liệu không hợp lệ', $validator->errors()->all());
          continue;
        }

        // Lưu lại dòng đã validate (kèm flag update/create)
        $this->validated_rows[] = [
          'index'        => $index,
          'raw'          => $raw,
          'row'          => $rowData,
          'is_update'    => (bool) $existing,
          'existing_id'  => $existing?->id,
        ];
      } catch (Exception $e) {
        $this->logAndAddFailedResult($index, $item, 'Lỗi hệ thống', [$e->getMessage()]);
      }
    }
  }

  /**
   * Upsert từng dòng đã validate (per-row, transaction ngắn gọn)
   */
  protected function upsertValidatedRows(): void
  {
    foreach ($this->validated_rows as $valid) {
      $excelRowIndex = (int)$valid['index'] + 2; // +2 vì header ở dòng 1, dữ liệu bắt đầu từ dòng 2
      $raw           = $valid['raw'];
      $data          = $valid['row'];
      $isUpdate      = $valid['is_update'];
      $existingId    = $valid['existing_id'];

      try {
        $userId = Auth::id() ?? 0;

        // Lọc ID hợp lệ trước khi sync (loại mồ côi)
        $validDvtIds = !empty($data['don_vi_tinh_id'])
          ? DonViTinh::whereIn('id', $data['don_vi_tinh_id'])->pluck('id')->all()
          : [];

        $validNccIds = !empty($data['nha_cung_cap_id'])
          ? NhaCungCap::whereIn('id', $data['nha_cung_cap_id'])->pluck('id')->all()
          : [];

        // Chuẩn bị pivot payload (SYNC)
        $pivotDvt = [];
        foreach ($validDvtIds as $id) {
          $pivotDvt[(int)$id] = ['nguoi_tao' => $userId, 'nguoi_cap_nhat' => $userId];
        }

        $pivotNcc = [];
        foreach ($validNccIds as $id) {
          $pivotNcc[(int)$id] = ['nguoi_tao' => $userId, 'nguoi_cap_nhat' => $userId];
        }

        if ($isUpdate) {
          // ================== UPDATE ==================
          /** @var SanPham $model */
          $model = SanPham::findOrFail($existingId);

          // Xây update data: bỏ các trường không thuộc cột của san_phams
          $update = $data;
          unset($update['don_vi_tinh_id'], $update['nha_cung_cap_id'], $update['image']);

          // Theo yêu cầu: nếu danh_muc_id rỗng → cố gắng để NULL nếu DB cho phép;
          // để tuyệt đối an toàn (không vỡ NOT NULL), ta mặc định "không đụng" khi rỗng.
          if (!array_key_exists('danh_muc_id', $data) || $data['danh_muc_id'] === null) {
            unset($update['danh_muc_id']);
          }

          $model->fill($update);
          $model->save();

          // Ảnh: nếu có thì replace path cũ (events trong Image đã xử lý delete file cũ)
          if (!empty($data['image'])) {
            $first = $model->images()->first();
            if ($first) {
              $first->update(['path' => $data['image']]);
            } else {
              $model->images()->create(['path' => $data['image']]);
            }
          }

          // SYNC pivot (ghi đè danh sách)
          if (!empty($pivotDvt)) {
            $model->donViTinhs()->sync($pivotDvt);
          } else {
            // Cho phép sync về rỗng nếu Excel để trống (theo SYNC toàn phần)
            $model->donViTinhs()->sync([]);
          }

          // Chỉ attach NCC khi loại là SP_NHA_CUNG_CAP hoặc NGUYEN_LIEU (đúng kinh doanh)
          $rowLoai = $data['loai_san_pham'] ?? $model->loai_san_pham;
          if (in_array($rowLoai, ['SP_NHA_CUNG_CAP', 'NGUYEN_LIEU'], true)) {
            $model->nhaCungCaps()->sync($pivotNcc);
          } else {
            // SP_SAN_XUAT: đảm bảo không gán NCC
            $model->nhaCungCaps()->sync([]);
          }

          $this->thanh_cong++;
          $this->updated++;

          $this->ket_qua_import[] = [
            'dong'      => $excelRowIndex,
            'ma_sp'     => $data['ma_san_pham'],
            'trang_thai'=> 'updated',
            'thong_bao' => 'Cập nhật thành công',
            'id'        => $model->id,
            'canh_bao'  => $this->buildWarnings($data, $validDvtIds, $validNccIds),
          ];
        } else {
          // ================== CREATE ==================
          // Lưu ý: CreateSanPhamRequest vẫn yêu cầu danh_muc_id (theo schema chuẩn).
          // Nếu rỗng → bỏ qua dòng & báo lỗi, KHÔNG chặn cả lô (đã được validate trước, nhưng giữ phòng xa).
          if (!isset($data['danh_muc_id']) || $data['danh_muc_id'] === null) {
            $this->addFailedResult(
              $valid['index'],
              $raw,
              'Thiếu danh_muc_id cho bản ghi mới',
              ['danh_muc_id không được rỗng khi tạo mới']
            );
            continue;
          }

          $create = $data;
          unset($create['don_vi_tinh_id'], $create['nha_cung_cap_id'], $create['image']);

          /** @var SanPham $model */
          $model = $this->model_class::create($create);

          // Ảnh
          if (!empty($data['image'])) {
            $model->images()->create(['path' => $data['image']]);
          }

          // SYNC ĐVT
          $model->donViTinhs()->sync($pivotDvt);

          // NCC chỉ áp dụng cho SP_NHA_CUNG_CAP, NGUYEN_LIEU
          $rowLoai = $data['loai_san_pham'] ?? null;
          if (in_array($rowLoai, ['SP_NHA_CUNG_CAP', 'NGUYEN_LIEU'], true)) {
            $model->nhaCungCaps()->sync($pivotNcc);
          }

          $this->thanh_cong++;
          $this->created++;

          $this->ket_qua_import[] = [
            'dong'      => $excelRowIndex,
            'ma_sp'     => $data['ma_san_pham'],
            'trang_thai'=> 'created',
            'thong_bao' => 'Tạo mới thành công',
            'id'        => $model->id,
            'canh_bao'  => $this->buildWarnings($data, $validDvtIds, $validNccIds),
          ];
        }
      } catch (Exception $e) {
        $this->logAndAddFailedResult($excelRowIndex, $raw, 'Lỗi hệ thống khi ghi dữ liệu', [$e->getMessage()]);
      }
    }
  }

  /**
   * Cảnh báo nhẹ nhàng (ID mồ côi bị loại trước khi sync)
   */
  protected function buildWarnings(array $row, array $validDvtIds, array $validNccIds): array
  {
    $warnings = [];

    if (!empty($row['don_vi_tinh_id'])) {
      $missing = array_values(array_diff($row['don_vi_tinh_id'], $validDvtIds));
      if (!empty($missing)) {
        $warnings[] = 'Một số ĐVT không tồn tại và đã bị loại: ' . implode(', ', $missing);
      }
    }

    if (!empty($row['nha_cung_cap_id'])) {
      $missing = array_values(array_diff($row['nha_cung_cap_id'], $validNccIds));
      if (!empty($missing)) {
        $warnings[] = 'Một số NCC không tồn tại và đã bị loại: ' . implode(', ', $missing);
      }
    }

    return $warnings;
  }

  /**
   * Ghi nhận thất bại 1 dòng
   */
  protected function addFailedResult(int $index, array $item, string $message, array $errors): void
  {
    $this->that_bai++;
    $this->ket_qua_import[] = [
      'dong'       => $index + 2,
      'du_lieu'    => $item,
      'trang_thai' => 'that_bai',
      'thong_bao'  => $message,
      'loi'        => $errors,
    ];
  }

  /**
   * Ghi log + mark thất bại
   */
  protected function logAndAddFailedResult($index, $item, $message, $errors): void
  {
    logger()->error($errors[0] ?? 'Lỗi không xác định');
    $this->addFailedResult($index, $item, $message, is_array($errors) ? $errors : [$errors]);
  }

  // ====== Getters giữ nguyên API cũ ======

  public function getThanhCong()
  {
    return $this->thanh_cong;
  }

  public function getThatBai()
  {
    return $this->that_bai;
  }

  public function getKetQuaImport()
  {
    return $this->ket_qua_import;
  }

  /**
   * Lưu lịch sử import
   */
  public function saveLichSuImport(): void
  {
    $payload = [
      'muc_import'           => $this->muc_import,
      'tong_so_luong'        => $this->tong_so_luong,
      'so_luong_thanh_cong'  => $this->thanh_cong,
      'so_luong_that_bai'    => $this->that_bai,
      'ket_qua_import'       => json_encode($this->ket_qua_import, JSON_UNESCAPED_UNICODE),
    ];

    // Gắn thêm thống kê chi tiết created/updated (không phá vỡ schema cũ—đưa vào JSON)
    try {
      $summary = [
        'created' => $this->created,
        'updated' => $this->updated,
      ];
      $detail = [
        'summary' => $summary,
        'rows'    => $this->ket_qua_import,
      ];
      $payload['ket_qua_import'] = json_encode($detail, JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
      // fallback đã set ở trên
    }

    LichSuImport::create($payload);
  }
}
