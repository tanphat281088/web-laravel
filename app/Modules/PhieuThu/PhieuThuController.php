<?php

namespace App\Modules\PhieuThu;

use App\Http\Controllers\Controller;
use App\Modules\PhieuThu\Validates\CreatePhieuThuRequest;
use App\Modules\PhieuThu\Validates\UpdatePhieuThuRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PhieuThuImport;
use Illuminate\Support\Str;
use App\Models\PhieuThu; // MỚI: dùng hằng TYPE_TAI_CHINH

class PhieuThuController extends Controller
{
    protected $phieuThuService;
    
    public function __construct(PhieuThuService $phieuThuService)
    {
        $this->phieuThuService = $phieuThuService;
    }
    
    /**
     * Lấy danh sách PhieuThus
     */
    public function index(Request $request)
    {
        $params = $request->all();

        // Xử lý và validate parameters
        $params = Helper::validateFilterParams($params);

        $result = $this->phieuThuService->getAll($params);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success([
          'collection' => $result['data'],
          'total' => $result['total'],
          'pagination' => $result['pagination'] ?? null
        ]);
    }
    
    /**
     * Tạo mới PhieuThu
     */
    public function store(CreatePhieuThuRequest $request)
    {
        // MỚI: chuẩn hoá trường "loại" (loai|loai_phieu_thu) về UPPERCASE (hỗ trợ TAI_CHINH)
        $data = $request->validated();
        if (isset($data['loai']) && is_string($data['loai'])) {
            $data['loai'] = strtoupper(trim($data['loai']));
        }
        if (isset($data['loai_phieu_thu']) && is_string($data['loai_phieu_thu'])) {
            $data['loai_phieu_thu'] = strtoupper(trim($data['loai_phieu_thu']));
        }

        $result = $this->phieuThuService->create($data);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result, 'Tạo mới thành công');
    }
    
    /**
     * Lấy thông tin PhieuThu
     */
    public function show($id)
    {
        $result = $this->phieuThuService->getById($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result);
    }
    
    /**
     * Cập nhật PhieuThu
     */
    public function update(UpdatePhieuThuRequest $request, $id)
    {
        // MỚI: chuẩn hoá trường "loại"
        $data = $request->validated();
        if (isset($data['loai']) && is_string($data['loai'])) {
            $data['loai'] = strtoupper(trim($data['loai']));
        }
        if (isset($data['loai_phieu_thu']) && is_string($data['loai_phieu_thu'])) {
            $data['loai_phieu_thu'] = strtoupper(trim($data['loai_phieu_thu']));
        }

        $result = $this->phieuThuService->update($id, $data);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result, 'Cập nhật thành công');
    }
    
    /**
     * Xóa PhieuThu
     */
    public function destroy($id)
    {
        $result = $this->phieuThuService->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success([], 'Xóa thành công');
    }

    /**
     * Lấy danh sách PhieuThu dạng option
     */
    public function getOptions()
    {
      $result = $this->phieuThuService->getOptions();

      if ($result instanceof \Illuminate\Http\JsonResponse) {
        return $result;
      }

      return CustomResponse::success($result);
    }

    /**
     * MỚI: Lấy danh sách LOẠI phiếu thu (combobox), bổ sung TAI_CHINH.
     * - Nếu service có hàm getLoaiOptions() thì dùng, không thì fallback mảng mặc định.
     */
    public function loaiOptions()
    {
        // Nếu service có sẵn:
        if (method_exists($this->phieuThuService, 'getLoaiOptions')) {
            $opts = $this->phieuThuService->getLoaiOptions();
            if ($opts instanceof \Illuminate\Http\JsonResponse) return $opts;

            // Bảo đảm có TAI_CHINH
            $hasFinancial = false;
            foreach ((array)$opts as $o) {
                if (isset($o['value']) && strtoupper($o['value']) === PhieuThu::TYPE_TAI_CHINH) {
                    $hasFinancial = true; break;
                }
            }
            if (!$hasFinancial) {
                $opts[] = ['value'=>PhieuThu::TYPE_TAI_CHINH, 'label'=>'Thu hoạt động tài chính'];
            }
            return CustomResponse::success($opts);
        }

        // Fallback: trả mảng mặc định (bạn có thể merge với 4 loại hiện hữu trên FE)
        $defaults = [
            // Các loại bán hàng hiện hữu của bạn có thể thêm ở đây
            // ['value'=>'BAN_HANG_TIEN_MAT', 'label'=>'Thu bán hàng - tiền mặt'],
            // ['value'=>'BAN_HANG_CHUYEN_KHOAN', 'label'=>'Thu bán hàng - chuyển khoản'],
            // ['value'=>'BAN_HANG_QUET_THE', 'label'=>'Thu bán hàng - quẹt thẻ'],
            // ['value'=>'BAN_HANG_VNPAY', 'label'=>'Thu bán hàng - ví/QR'],
            ['value'=>PhieuThu::TYPE_TAI_CHINH, 'label'=>'Thu hoạt động tài chính'], // MỚI
        ];
        return CustomResponse::success($defaults);
    }

    public function downloadTemplateExcel()
    {
      $path = public_path('mau-excel/PhieuThu.xlsx');

      if (!file_exists($path)) {
        return CustomResponse::error('File không tồn tại');
      }

      return response()->download($path);
    }

    public function importExcel(Request $request)
    {
      $request->validate([
        'file' => 'required|file|mimes:xlsx,xls,csv',
      ]);

      try {
        $data = $request->file('file');
        $filename = Str::random(10) . '.' . $data->getClientOriginalExtension();
        $path = $data->move(public_path('excel'), $filename);

        $import = new PhieuThuImport();
        Excel::import($import, $path);

        $thanhCong = $import->getThanhCong();
        $thatBai = $import->getThatBai();

        if ($thatBai > 0) {
          return CustomResponse::error('Import không thành công. Có ' . $thatBai . ' bản ghi lỗi và ' . $thanhCong . ' bản ghi thành công');
        }

        return CustomResponse::success([
          'success' => $thanhCong,
          'fail' => $thatBai
        ], 'Import thành công ' . $thanhCong . ' bản ghi');
      } catch (\Exception $e) {
        return CustomResponse::error('Lỗi import: ' . $e->getMessage(), 500);
      }
    }
}
