<?php

namespace App\Http\Controllers\api;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Class\Helper;
use App\Http\Controllers\Controller;
use App\Models\LichSuImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LichSuImportController extends Controller
{
  public function index(Request $request)
  {
    try {
      // Lấy tất cả parameters từ request
      $params = $request->all();

      // Xử lý và validate parameters
      $params = Helper::validateFilterParams($params);

      if (Auth::user()->vai_tro == 'admin') {
        $query = LichSuImport::query();
      } else {
        $query = LichSuImport::query()->where('nguoi_tao', Auth::user()->id);
      }

      // Sử dụng FilterWithPagination để xử lý filter và pagination
      $result = FilterWithPagination::findWithPagination(
        $query,
        $params,
        ['lich_su_imports.*'] // Columns cần select
      );

      $data = [
        'data' => $result['collection'],
        'total' => $result['total'],
        'pagination' => [
          'current_page' => $result['current_page'],
          'last_page' => $result['last_page'],
          'from' => $result['from'],
          'to' => $result['to'],
          'total_current' => $result['total_current']
        ]
      ];

      return CustomResponse::success($data);
    } catch (\Exception $e) {
      return CustomResponse::error($e->getMessage(), 500);
    }
  }

  public function downloadFile($id)
  {
    try {
      $lichSuImport = LichSuImport::find($id);

      if (!$lichSuImport) {
        return CustomResponse::error('Không tìm thấy lịch sử import.', 404);
      }

      $filePath = $lichSuImport->file_path;

      if (!$filePath || !file_exists($filePath)) {
        return CustomResponse::error('Tệp không tồn tại.', 404);
      }

      return response()->download($filePath);
    } catch (\Exception $e) {
      return CustomResponse::error($e->getMessage(), 500);
    }
  }
}