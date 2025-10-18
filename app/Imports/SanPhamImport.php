<?php

namespace App\Imports;

use App\Models\SanPham;
use App\Models\DonViTinh;
use App\Models\NhaCungCap;
use App\Modules\SanPham\Validates\CreateSanPhamRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Models\LichSuImport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SanPhamImport implements ToCollection, WithMultipleSheets
{
  // TODO: Config các thông tin cần thiết cho import
  protected $muc_import = 'Sản phẩm';
  protected $model_class = SanPham::class;
  protected $createRequest = CreateSanPhamRequest::class;

  protected $thanh_cong = 0;
  protected $that_bai = 0;
  protected $ket_qua_import = [];
  protected $validated_data = [];
  protected $tong_so_luong = 0;

  /**
   * Validate tất cả dữ liệu
   * @param array $data
   * @return void
   */
  protected function validateAllData(array $data)
  {
    // Lấy rules từ Request
    $createRequest = new $this->createRequest();
    $rules = $createRequest->rules();
    $messages = $createRequest->messages();

    foreach ($data as $index => $item) {
      try {
        // |--------------------------------------------------------|
        // TODO: THAY ĐỔI CHO PHÙ HỢP VỚI CÁC TRƯỜNG TRONG createRequest VÀ TRONG DATABASE
        $donViTinhId = !empty($item[5]) ? explode(',', $item[5]) : [];
        $nhaCungCapId = !empty($item[6]) ? explode(',', $item[6]) : [];

        $rowData = [
          'ma_san_pham'        => $item[1],
          'ten_san_pham'       => $item[2],
          'danh_muc_id'        => $item[3],
          'loai_san_pham'      => $item[4],
          'gia_nhap_mac_dinh'  => (int) ($item[7] ?? 0),   // Giá đặt ngay
          'gia_dat_truoc_3n'   => (int) ($item[8] ?? 0),   // ✅ GIÁ ĐẶT TRƯỚC 3 NGÀY (MỚI)
          'don_vi_tinh_id'     => $donViTinhId,
          'nha_cung_cap_id'    => $nhaCungCapId,
          'ty_le_chiet_khau'   => (int) ($item[9]  ?? 0),
          'muc_loi_nhuan'      => (int) ($item[10] ?? 0),
          'so_luong_canh_bao'  => (int) ($item[11] ?? 0),
          'trang_thai'         => (int) ($item[12] ?? 1),
          'ghi_chu'            => $item[13] ?? "",
        ];

        // |--------------------------------------------------------|

        // Validate dữ liệu với callback after
        $validator = Validator::make($rowData, $rules, $messages);

        // Gọi withValidator để thực hiện các validation bổ sung
        if (method_exists($createRequest, 'withValidator')) {
          // Thiết lập dữ liệu cho request để withValidator có thể truy cập
          $createRequest->merge($rowData);

          // Gọi withValidator với validator instance
          $createRequest->withValidator($validator);
        }

        if ($validator->fails()) {
          $this->addFailedResult($index, $item, 'Lỗi dữ liệu không hợp lệ', $validator->errors()->all());
        } else {
          $this->validated_data[] = [
            'rawData' => $item,
            'rowData' => $rowData,
            'index' => $index,
            'item' => $item,
            'donViTinhId' => $donViTinhId,
            'nhaCungCapId' => $nhaCungCapId
          ];
        }
      } catch (Exception $e) {
        $this->logAndAddFailedResult($index, $item, 'Lỗi hệ thống', [$e->getMessage()]);
      }
    }
  }

  public function sheets(): array
  {
    return [
      0 => $this // Chỉ đọc sheet đầu tiên (index 0)
    ];
  }

  /**
   * @param Collection $collection
   */
  public function collection(Collection $collection)
  {
    $data = array_slice($collection->toArray(), 1); // Bỏ qua dòng đầu tiên (header)

    $this->validateAllData($data);
    $this->tong_so_luong = count($data);

    // Chỉ tạo bản ghi khi tất cả dữ liệu đều hợp lệ
    if ($this->that_bai === 0 && count($this->validated_data) > 0) {
      $this->createRecords();
    }

    $this->saveLichSuImport();
  }

  /**
   * Tạo các bản ghi từ dữ liệu đã validate
   * @return void
   */
  protected function createRecords()
  {
    foreach ($this->validated_data as $valid_item) {
      try {
        // Loại bỏ các trường không cần thiết khi tạo model
        $rowDataToCreate = array_diff_key($valid_item['rowData'], [
          'don_vi_tinh_id' => [],
          'nha_cung_cap_id' => []
        ]);

        $model = $this->model_class::create($rowDataToCreate);

        if ($valid_item['rawData'][0]) {
          $model->images()->create([
            'path' => $valid_item['rawData'][0],
          ]);
        }

        $donViTinhId = $valid_item['donViTinhId'];
        if (!empty($donViTinhId)) {
          $model->donViTinhs()->attach($donViTinhId, [
            'nguoi_tao' => Auth::user()->id,
            'nguoi_cap_nhat' => Auth::user()->id
          ]);
        }

        // ✅ Chỉ attach NCC cho SP_NHA_CUNG_CAP | NGUYEN_LIEU và chỉ với ID hợp lệ
        $rowLoai = $valid_item['rowData']['loai_san_pham'] ?? null;
        $nhaCungCapId = $valid_item['nhaCungCapId'];

        if (in_array($rowLoai, ['SP_NHA_CUNG_CAP', 'NGUYEN_LIEU'], true) && !empty($nhaCungCapId)) {
            $validNccIds = \App\Models\NhaCungCap::whereIn('id', $nhaCungCapId)->pluck('id')->all();
            if (!empty($validNccIds)) {
                $model->nhaCungCaps()->attach($validNccIds, [
                    'nguoi_tao' => Auth::user()->id,
                    'nguoi_cap_nhat' => Auth::user()->id
                ]);
            }
        }
        // Trường hợp SP_SAN_XUAT hoặc không có NCC hợp lệ → bỏ qua, tránh vỡ FK


        $this->thanh_cong++;

        $this->ket_qua_import[] = [
          'dong' => $valid_item['index'] + 2,
          'du_lieu' => $valid_item['item'],
          'trang_thai' => 'thanh_cong',
          'thong_bao' => 'Import thành công',
          'id' => $model->id
        ];
      } catch (Exception $e) {
        $this->logAndAddFailedResult($valid_item['index'] + 2, $valid_item['item'], 'Lỗi hệ thống khi tạo bản ghi', [$e->getMessage()]);
      }
    }
  }

  /**
   * Thêm kết quả thất bại vào danh sách
   * @param int $index
   * @param array $item
   * @param string $message
   * @param array $errors
   * @return void
   */
  protected function addFailedResult($index, $item, $message, $errors)
  {
    $this->that_bai++;
    $this->ket_qua_import[] = [
      'dong' => $index + 2,
      'du_lieu' => $item,
      'trang_thai' => 'that_bai',
      'thong_bao' => $message,
      'loi' => $errors
    ];
  }

  /**
   * Ghi log và thêm kết quả thất bại
   * @param int $index
   * @param array $item
   * @param string $message
   * @param array $errors
   * @return void
   */
  protected function logAndAddFailedResult($index, $item, $message, $errors)
  {
    logger()->error($errors[0] ?? 'Lỗi không xác định');
    $this->addFailedResult($index, $item, $message, $errors);
  }

  /**
   * Lấy số bản ghi thành công
   * @return int
   */
  public function getThanhCong()
  {
    return $this->thanh_cong;
  }

  /**
   * Lấy số bản ghi thất bại
   * @return int
   */
  public function getThatBai()
  {
    return $this->that_bai;
  }

  /**
   * Lấy kết quả import
   * @return array
   */
  public function getKetQuaImport()
  {
    return $this->ket_qua_import;
  }

  /**
   * Lưu lịch sử import
   * @return void
   */
  public function saveLichSuImport()
  {
    LichSuImport::create([
      'muc_import' => $this->muc_import,
      'tong_so_luong' => $this->tong_so_luong,
      'so_luong_thanh_cong' => $this->thanh_cong,
      'so_luong_that_bai' => $this->that_bai,
      'ket_qua_import' => json_encode($this->ket_qua_import, JSON_UNESCAPED_UNICODE)
    ]);
  }
}