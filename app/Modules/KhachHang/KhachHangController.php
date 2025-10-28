<?php

namespace App\Modules\KhachHang;

use App\Http\Controllers\Controller;
use App\Modules\KhachHang\Validates\CreateKhachHangRequest;
use App\Modules\KhachHang\Validates\UpdateKhachHangRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\KhachHangImport;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use App\Models\LoaiKhachHang;

class KhachHangController extends Controller
{
    protected $khachHangService;

    public function __construct(KhachHangService $khachHangService)
    {
        $this->khachHangService = $khachHangService;
    }

    /**
     * Lấy danh sách KhachHangs
     */
    public function index(Request $request)
    {
        $params = $request->all();
        $params = Helper::validateFilterParams($params);

        $result = $this->khachHangService->getAll($params);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([
            'collection' => $result['data'],
            'total'      => $result['total'],
            'pagination' => $result['pagination'] ?? null,
        ]);
    }

    /**
     * Tạo mới KhachHang (đã hỗ trợ kenh_lien_he)
     */
    public function store(CreateKhachHangRequest $request)
    {
        $result = $this->khachHangService->create($request->validated());
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }
        return CustomResponse::success($result, 'Tạo mới thành công');
    }

    /**
     * Lấy 1 KhachHang
     */
    public function show($id)
    {
        $result = $this->khachHangService->getById($id);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }
        return CustomResponse::success($result);
    }

    /**
     * Cập nhật KhachHang (đã hỗ trợ kenh_lien_he)
     */
    public function update(UpdateKhachHangRequest $request, $id)
    {
        $result = $this->khachHangService->update($id, $request->validated());
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }
        return CustomResponse::success($result, 'Cập nhật thành công');
    }

    /**
     * Xoá KhachHang
     */
    public function destroy($id)
    {
        $result = $this->khachHangService->delete($id);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }
        return CustomResponse::success([], 'Xóa thành công');
    }

public function getOptions(\Illuminate\Http\Request $request)
{
    // nếu muốn vẫn dùng helper thì:
    // $params = \App\Class\Helper::validateFilterParams($request->all());
    $params = $request->all();

    $result = $this->khachHangService->getOptions($params);
    if ($result instanceof \Illuminate\Http\JsonResponse) {
        return $result;
    }
    return \App\Class\CustomResponse::success($result);
}


    /**
     * Tải excel mẫu:
     * - Đọc file mẫu gốc /public/mau-excel/KhachHang.xlsx
     * - Chèn cột "Kênh liên hệ" (sau "Số điện thoại") nếu chưa có
     * - Áp dropdown cố định theo config('kenh_lien_he.options')
     */
    public function downloadTemplateExcel()
    {
        $path = public_path('mau-excel/KhachHang.xlsx');
        if (!file_exists($path)) {
            return CustomResponse::error('File không tồn tại');
        }

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            // Tìm cột "Số điện thoại" để chèn "Kênh liên hệ" ngay phía sau
            $headerRow = 1;
            $lastColumn = $sheet->getHighestColumn();
            $lastColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn);

            $colSoDienThoaiIndex = null;
            $colKenhLienHeIndex  = null;

            for ($col = 1; $col <= $lastColumnIndex; $col++) {
                $value = trim((string) $sheet->getCellByColumnAndRow($col, $headerRow)->getValue());
                if ($value === 'Số điện thoại') {
                    $colSoDienThoaiIndex = $col;
                }
                if ($value === 'Kênh liên hệ') {
                    $colKenhLienHeIndex = $col;
                }
            }

            // Nếu chưa có cột "Kênh liên hệ" thì chèn sau cột "Số điện thoại"
            if (is_null($colKenhLienHeIndex)) {
                // Nếu không tìm thấy "Số điện thoại", ta chèn cuối bảng cho an toàn
                $insertIndex = $colSoDienThoaiIndex ? $colSoDienThoaiIndex + 1 : ($lastColumnIndex + 1);
                $insertColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($insertIndex);
                // Chèn 1 cột trống
                $sheet->insertNewColumnBefore($insertColLetter, 1);
                // Ghi header
                $sheet->setCellValue($insertColLetter . $headerRow, 'Kênh liên hệ');
                $colKenhLienHeIndex = $insertIndex;
            }

            // Áp data validation (dropdown) cho cột "Kênh liên hệ"
            $options = (array) config('kenh_lien_he.options', []);
            // Gộp chuỗi theo format "A,B,C" trong công thức excel => phải bọc trong dấu "
            $list = '"' . implode(',', array_map(fn ($v) => str_replace(',', ' ', $v), $options)) . '"';

            // Áp cho từ hàng 2 -> 1000 (đủ dùng; khi cần có thể tăng)
            $startRow = 2;
            $endRow   = 1000;
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colKenhLienHeIndex);

            for ($row = $startRow; $row <= $endRow; $row++) {
                $cell = $sheet->getCell($colLetter . $row);
                $validation = $cell->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Giá trị không hợp lệ');
                $validation->setError('Vui lòng chọn trong danh sách kênh liên hệ.');
                $validation->setPromptTitle('Kênh liên hệ');
                $validation->setPrompt('Chọn một giá trị trong danh sách');
                $validation->setFormula1($list);
            }

            // Xuất file tạm và download
            $tempPath = storage_path('app/temp_khachhang_' . time() . '.xlsx');
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, 'KhachHang.xlsx')->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return CustomResponse::error('Lỗi tạo file mẫu: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import Excel
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $data = $request->file('file');
            $filename = Str::random(10) . '.' . $data->getClientOriginalExtension();
            $path = $data->move(public_path('excel'), $filename);

            $import = new KhachHangImport($path);
            Excel::import($import, $path);

            $thanhCong = $import->getThanhCong();
            $thatBai   = $import->getThatBai();

            if ($thatBai > 0) {
                return CustomResponse::error('Import không thành công. Có ' . $thatBai . ' bản ghi lỗi và ' . $thanhCong . ' bản ghi thành công');
            }

            return CustomResponse::success([
                'success' => $thanhCong,
                'fail'    => $thatBai,
            ], 'Import thành công ' . $thanhCong . ' bản ghi');
        } catch (\Exception $e) {
            return CustomResponse::error('Lỗi import: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download template Excel có thêm sheet Loại Khách Hàng (giữ nguyên)
     */
    public function downloadTemplateExcelWithLoaiKhachHang()
    {
        $fileName = "KhachHang";
        try {
            $path = public_path('mau-excel/' . $fileName . '.xlsx');
            $spreadsheet = IOFactory::load($path);

            $newWorksheet = $spreadsheet->createSheet();
            $newWorksheet->setTitle('Loại Khách Hàng');

            $loaiKhachHangs = LoaiKhachHang::select('id', 'ten_loai_khach_hang')
                ->where('trang_thai', 1)->get();

            $newWorksheet->setCellValue('A1', 'ID');
            $newWorksheet->setCellValue('B1', 'Tên Loại Khách Hàng');
            $newWorksheet->getStyle('A1:B1')->getFont()->setBold(true);
            $newWorksheet->getStyle('A1:B1')->getAlignment()->setHorizontal('center');

            $row = 2;
            foreach ($loaiKhachHangs as $loaiKhachHang) {
                $newWorksheet->setCellValue('A' . $row, $loaiKhachHang->id);
                $newWorksheet->setCellValue('B' . $row, $loaiKhachHang->ten_loai_khach_hang);
                $row++;
            }
            $newWorksheet->getColumnDimension('A')->setAutoSize(true);
            $newWorksheet->getColumnDimension('B')->setAutoSize(true);

            $tempPath = storage_path('app/temp_excel_' . time() . '.xlsx');
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, $fileName . '.xlsx')->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return CustomResponse::error('Lỗi tạo file Excel: ' . $e->getMessage(), 500);
        }
    }
}
