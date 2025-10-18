<?php

namespace App\Http\Middleware;

use App\Class\CustomResponse;
use App\Class\Helper;
use App\Models\VaiTro;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Permission
{
  protected $excludedRoutes = [
    'api/auth/me',
    'api/auth/logout',
    'api/upload/single',
    'api/upload/multiple',
    'api/danh-sach-phan-quyen',
    'api/auth/profile'
  ];


  public function handle(Request $request, Closure $next): Response
  {
    if ($this->shouldExcludeRoute($request->path())) {
      return $next($request);
    }

    $user = Auth::user();

    if (!$user || !$user->vaiTro || !$user->vaiTro->phan_quyen || $user->vaiTro->trang_thai != 1) {
      return CustomResponse::error(
        "Không xác định được vai trò",
        Response::HTTP_UNAUTHORIZED
      );
    }

    $permission = json_decode($user->vaiTro->phan_quyen ?? "[]", true);

    $path = str_replace("api/", "", $request->path()); // nguoi-dung hoặc nguoi-dung/1
    $method = $request->method(); // GET, POST, PUT, DELETE

    $action = Helper::convertMethod($path, $method);

    $permission = collect($permission)->first(function ($item) use ($path, $action) {
      return str_contains($path, $item['name']) && $item['actions'][$action] === true;
    });

    if (!$permission) {
      return CustomResponse::error(
        "Bạn không có quyền truy cập vào nội dung này",
        Response::HTTP_FORBIDDEN
      );
    }

    return $next($request);
  }

  protected function shouldExcludeRoute(string $path): bool
  {
    // Kiểm tra route chính xác
    if (in_array($path, $this->excludedRoutes)) {
      return true;
    }

    if (str_contains($path, "options")) {
      return true;
    }

    if (str_contains($path, "import-excel")) {
      return true;
    }

    if (str_contains($path, "download-template-excel")) {
      return true;
    }

    if (str_contains($path, "lich-su-import")) {
      return true;
    }

    if (str_contains($path, "lich-su-import/download-file")) {
      return true;
    }

    // Kiểm tra cả route có tham số (ví dụ: thong-tin-ca-nhan/123)
    foreach ($this->excludedRoutes as $route) {
      if (strpos($path, $route) === 0) {
        return true;
      }
    }

    return false;
  }
}