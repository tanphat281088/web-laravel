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
        $result = $this->phieuThuService->create($request->validated());

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
        $result = $this->phieuThuService->update($id, $request->validated());

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
