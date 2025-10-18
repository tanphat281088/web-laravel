<?php

namespace App\Modules\PhieuXuatKho;

use App\Http\Controllers\Controller;
use App\Modules\PhieuXuatKho\Validates\CreatePhieuXuatKhoRequest;
use App\Modules\PhieuXuatKho\Validates\UpdatePhieuXuatKhoRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PhieuXuatKhoImport;
use Illuminate\Support\Str;

class PhieuXuatKhoController extends Controller
{
    protected $phieuXuatKhoService;
    
    public function __construct(PhieuXuatKhoService $phieuXuatKhoService)
    {
        $this->phieuXuatKhoService = $phieuXuatKhoService;
    }
    
    /**
     * Lấy danh sách PhieuXuatKhos
     */
    public function index(Request $request)
    {
        $params = $request->all();

        // Xử lý và validate parameters
        $params = Helper::validateFilterParams($params);

        $result = $this->phieuXuatKhoService->getAll($params);

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
     * Tạo mới PhieuXuatKho
     */
    public function store(CreatePhieuXuatKhoRequest $request)
    {
        $result = $this->phieuXuatKhoService->create($request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result, 'Tạo mới thành công');
    }
    
    /**
     * Lấy thông tin PhieuXuatKho
     */
    public function show($id)
    {
        $result = $this->phieuXuatKhoService->getById($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result);
    }
    
    /**
     * Cập nhật PhieuXuatKho
     */
    public function update(UpdatePhieuXuatKhoRequest $request, $id)
    {
        $result = $this->phieuXuatKhoService->update($id, $request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result, 'Cập nhật thành công');
    }
    
    /**
     * Xóa PhieuXuatKho
     */
    public function destroy($id)
    {
        $result = $this->phieuXuatKhoService->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success([], 'Xóa thành công');
    }

    /**
     * Lấy danh sách PhieuXuatKho dạng option
     */
    public function getOptions()
    {
      $result = $this->phieuXuatKhoService->getOptions();

      if ($result instanceof \Illuminate\Http\JsonResponse) {
        return $result;
      }

      return CustomResponse::success($result);
    }

    public function downloadTemplateExcel()
    {
      $path = public_path('mau-excel/PhieuXuatKho.xlsx');

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

      $import = new PhieuXuatKhoImport();
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
