<?php

namespace App\Modules\QuanLyCongNo;

use App\Http\Controllers\Controller;
use App\Modules\QuanLyCongNo\Validates\CreateQuanLyCongNoRequest;
use App\Modules\QuanLyCongNo\Validates\UpdateQuanLyCongNoRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\QuanLyCongNoImport;
use Illuminate\Support\Str;

class QuanLyCongNoController extends Controller
{
    protected $quanLyCongNoService;
    
    public function __construct(QuanLyCongNoService $quanLyCongNoService)
    {
        $this->quanLyCongNoService = $quanLyCongNoService;
    }
    
    /**
     * Lấy danh sách QuanLyCongNos
     */
    public function index(Request $request)
    {
        $params = $request->all();

        // Xử lý và validate parameters
        $params = Helper::validateFilterParams($params);

        $result = $this->quanLyCongNoService->getAll($params);

        return CustomResponse::success([
          'collection' => $result['data'],
          'total' => $result['total'],
          'pagination' => $result['pagination'] ?? null
        ]);
    }
    
    /**
     * Tạo mới QuanLyCongNo
     */
    public function store(CreateQuanLyCongNoRequest $request)
    {
        $result = $this->quanLyCongNoService->create($request->validated());
        return CustomResponse::success($result, 'Tạo mới thành công');
    }
    
    /**
     * Lấy thông tin QuanLyCongNo
     */
    public function show($id)
    {
        $result = $this->quanLyCongNoService->getById($id);
        return CustomResponse::success($result);
    }
    
    /**
     * Cập nhật QuanLyCongNo
     */
    public function update(UpdateQuanLyCongNoRequest $request, $id)
    {
        $result = $this->quanLyCongNoService->update($id, $request->validated());
        return CustomResponse::success($result, 'Cập nhật thành công');
    }
    
    /**
     * Xóa QuanLyCongNo
     */
    public function destroy($id)
    {
        $result = $this->quanLyCongNoService->delete($id);
        return CustomResponse::success([], 'Xóa thành công');
    }

    /**
     * Lấy danh sách QuanLyCongNo dạng option
     */
    public function getOptions()
    {
      $result = $this->quanLyCongNoService->getOptions();
      return CustomResponse::success($result);
    }

    public function downloadTemplateExcel()
    {
      $path = public_path('mau-excel/QuanLyCongNo.xlsx');
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

      $import = new QuanLyCongNoImport();
      Excel::import($import, $path);

      $thanhCong = $import->getThanhCong();
      $thatBai = $import->getThatBai();

      // Xóa file sau khi import
      if (file_exists($path)) {
        unlink($path);
      }

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
