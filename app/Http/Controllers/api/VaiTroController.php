<?php

namespace App\Http\Controllers\api;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Class\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VaiTro;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VaiTroController extends Controller
{
  public function index(Request $request)
  {
    $params = $request->all();

    $params = Helper::validateFilterParams($params);

    $query = VaiTro::query();

    // Sử dụng FilterWithPagination để xử lý filter và pagination
    $result = FilterWithPagination::findWithPagination(
      $query,
      $params,
      ['vai_tros.*'] // Columns cần select
    );

    return CustomResponse::success([
      'collection' => $result['collection'],
      'total' => $result['total'],
      'pagination' => null
    ]);
  }

  public function store(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'ma_vai_tro' => 'required|string|max:255',
      'ten_vai_tro' => 'required|string|max:255',
      'phan_quyen' => 'required|json',
    ]);

    if ($validator->fails()) {
      return CustomResponse::error("Thông tin không hợp lệ", $validator->errors());
    }

    try {
      $data = $request->all();
      $vaiTro = VaiTro::create($data);
      return CustomResponse::success($vaiTro);
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  public function show($id)
  {
    $vaiTro = VaiTro::find($id);
    return CustomResponse::success($vaiTro);
  }

  public function update(Request $request, $id)
  {
    $vaiTro = VaiTro::find($id);
    $vaiTro->update($request->all());
    return CustomResponse::success($vaiTro, "Cập nhật thành công");
  }

  public function destroy($id)
  {
    $vaiTro = VaiTro::find($id);
    $users = User::where('ma_vai_tro', $vaiTro->ma_vai_tro)->get();
    if ($users->count() > 0) {
      return CustomResponse::error("Vai trò đang được sử dụng cho " . $users->count() . " người dùng, không thể xóa");
    }
    $vaiTro->delete();
    return CustomResponse::success($vaiTro);
  }

  public function options()
  {
    $vaiTros = VaiTro::where('trang_thai', 1)->select('ma_vai_tro', 'ten_vai_tro')->get();
    $vaiTros = $vaiTros->map(function ($vaiTro) {
      return [
        'value' => $vaiTro->ma_vai_tro,
        'label' => $vaiTro->ten_vai_tro,
      ];
    });
    return CustomResponse::success($vaiTros);
  }
}