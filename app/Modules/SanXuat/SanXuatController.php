<?php

namespace App\Modules\SanXuat;

use App\Http\Controllers\Controller;
use App\Modules\SanXuat\Validates\CreateSanXuatRequest;
use App\Modules\SanXuat\Validates\UpdateSanXuatRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SanXuatImport;
use Illuminate\Support\Str;

class SanXuatController extends Controller
{
    protected $sanXuatService;
    
    public function __construct(SanXuatService $sanXuatService)
    {
        $this->sanXuatService = $sanXuatService;
    }
    
    /**
     * Lấy danh sách SanXuats
     */
    public function index(Request $request)
    {
        $params = $request->all();

        // Xử lý và validate parameters
        $params = Helper::validateFilterParams($params);

        $result = $this->sanXuatService->getAll($params);

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
     * Tạo mới SanXuat
     */
    public function store(CreateSanXuatRequest $request)
    {
        $result = $this->sanXuatService->create($request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result, 'Tạo mới thành công');
    }
    
    /**
     * Lấy thông tin SanXuat
     */
    public function show($id)
    {
        $result = $this->sanXuatService->getById($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result);
    }
    
    /**
     * Cập nhật SanXuat
     */
    public function update(UpdateSanXuatRequest $request, $id)
    {
        $result = $this->sanXuatService->update($id, $request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success($result, 'Cập nhật thành công');
    }
    
    /**
     * Xóa SanXuat
     */
    public function destroy($id)
    {
        $result = $this->sanXuatService->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
          return $result;
        }

        return CustomResponse::success([], 'Xóa thành công');
    }

    /**
     * Lấy danh sách SanXuat dạng option
     */
    public function getOptions(Request $request)
    {
      $params = $request->all();

      $params = Helper::validateFilterParams($params);

      $result = $this->sanXuatService->getOptions($params);

      if ($result instanceof \Illuminate\Http\JsonResponse) {
        return $result;
      }

      return CustomResponse::success($result);
    }

    public function downloadTemplateExcel()
    {
      $path = public_path('mau-excel/SanXuat.xlsx');

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

      $import = new SanXuatImport();
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
