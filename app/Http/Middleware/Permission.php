<?php

namespace App\Http\Middleware;

use App\Class\CustomResponse;
use App\Class\Helper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Permission
{
    /**
     * Những route bỏ qua kiểm tra quyền (chỉ cần JWT)
     */
    protected $excludedRoutes = [
        'api/auth/me',
        'api/auth/logout',
        'api/upload/single',
        'api/upload/multiple',
        'api/danh-sach-phan-quyen',
        'api/auth/profile',

        // ====== MỚI: mở quyền xem Báo cáo Quản trị (KQKD) ======
        'api/bao-cao-quan-tri/kqkd',
        'api/bao-cao-quan-tri/kqkd-detail',
        'api/bao-cao-quan-tri/kqkd-export',
        // ================================================

        // ====== MỚI: mở toàn bộ API Quản lý vật tư (VT) ======
        // Lưu ý: vì foreach ở dưới dùng "startsWith", các prefix này
        // sẽ bao trùm luôn các đường dẫn con: /{id}, /options, ...
        'api/vt/items',
        'api/vt/receipts',
        'api/vt/issues',
        'api/vt/stocks',
        'api/vt/ledger',
        // (nếu bạn bật import tồn đầu)
        'api/vt/items/import-opening',
        // ======================================================


    ];

    /**
     * Map alias quyền theo module.
     * Ví dụ: mọi route bắt đầu bằng "giao-hang" sẽ dùng quyền của "quan-ly-ban-hang".
     */
    protected $moduleAliasMap = [
        'giao-hang' => 'quan-ly-ban-hang',
        'nhan-su'   => 'nhan-su',   // map URL /nhan-su/... về module quyền 'nhan-su'
        'attendance'=> 'nhan-su',   // alias cho các URL /attendance/* dùng quyền của 'nhan-su'
        // (Không cần alias cho 'bao-cao-quan-tri' vì đã whitelist ở trên)
            'cskh/points' => 'cskh-points', // ✅ map URL /cskh/points/* -> module quyền 'cskh-points'

                    // ✅ VT (Quản lý vật tư): map URL -> module quyền
        // Khi bạn seed quyền thật, chỉ cần tạo các module bên trái:
        // vt, vt-items, vt-receipts, vt-issues, vt-stocks
        'vt'          => 'vt',
        'vt/items'    => 'vt-items',
        'vt/receipts' => 'vt-receipts',
        'vt/issues'   => 'vt-issues',
        'vt/stocks'   => 'vt-stocks',
        'vt/ledger'   => 'vt-stocks', // ledger xem như quyền xem tồn/sổ kho


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

        $permissionJson = $user->vaiTro->phan_quyen ?? "[]";
        $permissionArr  = json_decode($permissionJson, true) ?: [];

        // Ví dụ path: api/giao-hang/hom-nay  ->  giao-hang/hom-nay
        $path   = str_replace("api/", "", $request->path());
        $method = $request->method(); // GET | POST | PUT | PATCH | DELETE

        // Convert method + path thành action (logic gốc)
        $action = Helper::convertMethod($path, $method);

// ✅ Force action = index cho các endpoint liệt kê điểm thành viên
if (
    $method === 'GET' &&
    (
        preg_match('#^(api/)?cskh/points/events(\?|$)#', $request->path()) === 1
        || preg_match('#^(api/)?cskh/points/customers/\d+/events(\?|$)#', $request->path()) === 1
        || preg_match('#^(api/)?cskh-points/events(\?|$)#', $request->path()) === 1
        || preg_match('#^(api/)?cskh-points/customers/\d+/events(\?|$)#', $request->path()) === 1
    )
) {
    $action = 'index';
}




        // Áp dụng alias module nếu có
        $pathForCheck = $this->applyModuleAlias($path);

        // ==== PATCH: normalize Don Tu special actions ====
        // Path khớp: nhan-su/don-tu/{id}/(approve|reject|cancel) -> ép action = 'update' & module = 'nhan-su'
        if (
            $method === 'PATCH'
            && preg_match('#^nhan-su/don-tu/\d+/(approve|reject|cancel)$#', $path) === 1
        ) {
            $action       = 'update';
            $pathForCheck = 'nhan-su';
        }

        // (Tùy chọn) Debug nếu cần
        // \Log::info('PERM_DEBUG', compact('path','method','action','pathForCheck'));

        // Tìm 1 mục quyền thỏa: tên module khớp trong path & action được bật
        $granted = collect($permissionArr)->first(function ($item) use ($pathForCheck, $action) {
            return isset($item['name'], $item['actions'][$action])
                && $item['actions'][$action] === true
                && str_contains($pathForCheck, $item['name']);
        });

        if (!$granted) {
            return CustomResponse::error(
                "Bạn không có quyền truy cập vào nội dung này",
                Response::HTTP_FORBIDDEN // 403 thật
            );
        }

        return $next($request);
    }

    /**
     * Áp dụng alias module cho path:
     *  - Nếu path bắt đầu bằng key trong $moduleAliasMap, ta thay phần đầu path đó bằng
     *    tên module canonical để so khớp quyền.
     *    VD: "giao-hang/hom-nay" -> "quan-ly-ban-hang/hom-nay"
     */
    protected function applyModuleAlias(string $path): string
    {
        foreach ($this->moduleAliasMap as $alias => $canonical) {
            if ($path === $alias || str_starts_with($path, $alias . '/')) {
                return $canonical . substr($path, strlen($alias));
            }
        }
        return $path;
    }

    protected function shouldExcludeRoute(string $path): bool
    {
        // Kiểm tra route chính xác
        if (in_array($path, $this->excludedRoutes, true)) {
            return true;
        }

        // Một số pattern công khai
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
