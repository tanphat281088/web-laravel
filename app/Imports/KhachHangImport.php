<?php

namespace App\Imports;

use App\Models\KhachHang;
use App\Modules\KhachHang\Validates\CreateKhachHangRequest;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Models\LichSuImport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Facades\DB;

class KhachHangImport implements ToCollection, WithMultipleSheets
{
    protected $muc_import    = 'Khách hàng';
    protected $model_class   = KhachHang::class;
    protected $createRequest = CreateKhachHangRequest::class;

    protected $thanh_cong = 0;
    protected $that_bai   = 0;
    protected $ket_qua_import = [];
    protected $validated_data = [];
    protected $filePath;
    protected $tong_so_luong = 0;

    public function __construct($filePath = null)
    {
        $this->filePath = $filePath;
    }

    public function sheets(): array
    {
        // Chỉ đọc sheet đầu
        return [0 => $this];
    }

    private function cleanStr($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    /**
     * Chuẩn hoá số điện thoại:
     * - Giữ nguyên chỉ chữ số
     * - Nếu bắt đầu bằng "84" → đổi về "0" + phần còn lại
     * - Nếu KHÔNG bắt đầu bằng "0" và độ dài 9/10/11 → thêm "0" đầu (trường hợp Excel rớt 0)
     */
    private function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) return null;
        $digits = preg_replace('/\D+/', '', $raw);

        // 84xxxxxxxxx -> 0xxxxxxxxx
        if (strpos($digits, '84') === 0 && strlen($digits) >= 10) {
            $digits = '0' . substr($digits, 2);
        }

        if ($digits !== '' && $digits[0] !== '0') {
            $len = strlen($digits);
            if (in_array($len, [9,10,11], true)) {
                $digits = '0' . $digits;
            }
        }
        return $digits !== '' ? $digits : null;
    }

    /** Kiểm tra Mã KH có trùng không */
    private function isMaKhExists(string $ma_kh): bool
    {
        return DB::table('khach_hangs')->where('ma_kh', $ma_kh)->exists();
    }

    protected function validateAllData(array $data)
    {
        $createRequest = new $this->createRequest();
        $rules    = $createRequest->rules();
        $messages = $createRequest->messages();

        $allowedKenhLienHe = (array) config('kenh_lien_he.options', []);

        foreach ($data as $index => $item) {
            try {
                $cols = array_values($item);
                $colCount = count($cols);

                /**
                 * HỖ TRỢ 2 DẠNG FILE:
                 * A) File template MỚI (không có "Mã KH"):
                 *    [0] Tên KH | [1] Email | [2] SĐT | [3] Kênh | [4] Địa chỉ | [5] Loại KH id | [6] Công nợ | [7] Doanh thu TL | [8] Ghi chú
                 * B) File CM gốc của bạn (CÓ "Mã KH" ở cột đầu):
                 *    [0] Mã KH | [1] Tên KH | [2] Email | [3] SĐT | [4] Kênh | [5] Địa chỉ | [6] Loại KH id | [7] Công nợ | [8] Doanh thu TL | [9] Ghi chú
                 */
                $hasMaKh = false;
                $ma_kh = null;
                if ($colCount >= 10) {
                    // Heuristic: coi như có Mã KH ở cột 0
                    $hasMaKh = true;
                    $ma_kh   = $this->cleanStr($cols[0] ?? null);

                    $ten_khach_hang     = $this->cleanStr($cols[1] ?? null);
                    $email              = $this->cleanStr($cols[2] ?? null);
                    $so_dien_thoai_raw  = $this->cleanStr($cols[3] ?? null);
                    $kenh_lien_he       = $this->cleanStr($cols[4] ?? null);
                    $dia_chi            = $this->cleanStr($cols[5] ?? null);
                    $loai_khach_hang_id = $this->cleanStr($cols[6] ?? null);
                    $cong_no            = $cols[7] ?? 0;
                    $doanh_thu_tich_luy = $cols[8] ?? 0;
                    $ghi_chu            = $this->cleanStr($cols[9] ?? null);
                } else {
                    $ten_khach_hang     = $this->cleanStr($cols[0] ?? null);
                    $email              = $this->cleanStr($cols[1] ?? null);
                    $so_dien_thoai_raw  = $this->cleanStr($cols[2] ?? null);
                    $kenh_lien_he       = $this->cleanStr($cols[3] ?? null);
                    $dia_chi            = $this->cleanStr($cols[4] ?? null);
                    $loai_khach_hang_id = $this->cleanStr($cols[5] ?? null);
                    $cong_no            = $cols[6] ?? 0;
                    $doanh_thu_tich_luy = $cols[7] ?? 0;
                    $ghi_chu            = $this->cleanStr($cols[8] ?? null);
                }

                // Chuẩn hoá số điện thoại để giữ số 0 đầu
                $so_dien_thoai = $this->normalizePhone($so_dien_thoai_raw);

                // Kênh liên hệ: required + in(config), không phân biệt hoa/thường
                $okKenh = false;
                if ($kenh_lien_he !== null) {
                    foreach ($allowedKenhLienHe as $opt) {
                        if (mb_strtolower($opt) === mb_strtolower($kenh_lien_he)) {
                            $kenh_lien_he = $opt;
                            $okKenh = true; break;
                        }
                    }
                }
                if (!$okKenh) {
                    $this->addFailedResult($index, $item, 'Kênh liên hệ không hợp lệ', ['Phải chọn trong danh sách cố định của template.']);
                    continue;
                }

                // Build rowData theo CreateKhachHangRequest (email: nullable)
                $rowData = [
                    'ten_khach_hang'     => $ten_khach_hang,
                    'email'              => $email,
                    'so_dien_thoai'      => $so_dien_thoai,
                    'kenh_lien_he'       => $kenh_lien_he,
                    'dia_chi'            => $dia_chi ?? 'Chưa cập nhật',
                    'loai_khach_hang_id' => $loai_khach_hang_id,
                    'cong_no'            => $cong_no ?? 0,
                    'doanh_thu_tich_luy' => $doanh_thu_tich_luy ?? 0,
                    'ghi_chu'            => $ghi_chu,
                ];

                // Validate theo FormRequest
                $validator = Validator::make($rowData, $rules, $messages);
                if ($validator->fails()) {
                    $this->addFailedResult($index, $item, 'Lỗi dữ liệu không hợp lệ', $validator->errors()->all());
                    continue;
                }

                // Nếu file có Mã KH → kiểm tra trùng
                if ($hasMaKh && $ma_kh) {
                    if ($this->isMaKhExists($ma_kh)) {
                        $this->addFailedResult($index, $item, 'Trùng Mã KH', ["Mã KH '{$ma_kh}' đã tồn tại."]);
                        continue;
                    }
                    $rowData['ma_kh'] = $ma_kh; // GIỮ NGUYÊN MÃ KH TỪ FILE
                }

                $this->validated_data[] = [
                    'rowData' => $rowData,
                    'index'   => $index,
                    'item'    => $item
                ];
            } catch (Exception $e) {
                $this->logAndAddFailedResult($index, $item, 'Lỗi hệ thống', [$e->getMessage()]);
            }
        }
    }

    public function collection(Collection $collection)
    {
        // Bỏ header
        $data = array_slice($collection->toArray(), 1);

        $this->validateAllData($data);
        $this->tong_so_luong = count($data);

        if ($this->that_bai === 0 && count($this->validated_data) > 0) {
            $this->createRecords();
        }

        $this->saveLichSuImport();
    }

    protected function createRecords()
    {
        foreach ($this->validated_data as $valid_item) {
            try {
                // Tạo bản ghi (kể cả có ma_kh hay không)
                $model = $this->model_class::create($valid_item['rowData']);

                $this->thanh_cong++;
                $this->ket_qua_import[] = [
                    'dong'       => $valid_item['index'] + 2,
                    'du_lieu'    => $valid_item['item'],
                    'trang_thai' => 'thanh_cong',
                    'thong_bao'  => 'Import thành công',
                    'id'         => $model->id
                ];
            } catch (Exception $e) {
                $this->logAndAddFailedResult(
                    $valid_item['index'] + 2,
                    $valid_item['item'],
                    'Lỗi hệ thống khi tạo bản ghi',
                    [$e->getMessage()]
                );
            }
        }
    }

    protected function addFailedResult($index, $item, $message, $errors)
    {
        $this->that_bai++;
        $this->ket_qua_import[] = [
            'dong'       => $index + 2,
            'du_lieu'    => $item,
            'trang_thai' => 'that_bai',
            'thong_bao'  => $message,
            'loi'        => $errors
        ];
    }

    protected function logAndAddFailedResult($index, $item, $message, $errors)
    {
        logger()->error($errors[0] ?? 'Lỗi không xác định');
        $this->addFailedResult($index, $item, $message, $errors);
    }

    public function getThanhCong() { return $this->thanh_cong; }
    public function getThatBai()   { return $this->that_bai; }
    public function getKetQuaImport() { return $this->ket_qua_import; }

    public function saveLichSuImport()
    {
        LichSuImport::create([
            'muc_import'          => $this->muc_import,
            'tong_so_luong'       => $this->tong_so_luong,
            'so_luong_thanh_cong' => $this->thanh_cong,
            'so_luong_that_bai'   => $this->that_bai,
            'ket_qua_import'      => json_encode($this->ket_qua_import, JSON_UNESCAPED_UNICODE),
            'file_path'           => $this->filePath,
        ]);
    }
}
