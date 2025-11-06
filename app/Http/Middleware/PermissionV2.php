<?php

namespace App\Http\Middleware;

use App\Class\CustomResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionV2
{
    /** Module alias: prefix URL -> module canonical (khớp registry V2) */
    protected array $alias = [
        // Hệ thống phụ
        'attendance'           => 'nhan-su', // alias cũ (nếu còn dùng)
        'nhan-su'              => 'nhan-su',

        // CSKH (parent/child)
        'cskh/points'          => 'cskh-points',
        'cskh-points'          => 'cskh-points',
        'cskh'                 => 'cskh',         // menu cha

        // Utilities
        'utilities/fb'         => 'utilities-fb',
        'utilities/zl'         => 'utilities-zl',
        'utilities'            => 'utilities',    // menu cha

        // VT
        'vt/items'             => 'vt-items',
        'vt/receipts'          => 'vt-receipts',
        'vt/issues'            => 'vt-issues',
        'vt/stocks'            => 'vt-stocks',
        'vt/ledger'            => 'vt-stocks',    // ledger dùng quyền stocks

        // Cashflow
        'cash/accounts'        => 'cash-accounts',
        'cash/aliases'         => 'cash-aliases',
        'cash/ledger'          => 'cash-ledger',
        'cash/balances'        => 'cash-ledger',
        'cash/balances/summary'=> 'cash-ledger',
        'cash/internal-transfers' => 'cash-internal-transfers',


                // Công nợ KH (read-only)
        'cong-no'               => 'quan-ly-cong-no',
        'cong-no/summary'       => 'quan-ly-cong-no',
        'cong-no/customers'     => 'quan-ly-cong-no',
        'cong-no/export'        => 'quan-ly-cong-no',


        // Các module chuẩn 1:1
        'dashboard'            => 'dashboard',
        'vai-tro'              => 'vai-tro',
        'nguoi-dung'           => 'nguoi-dung',
        'cau-hinh-chung'       => 'cau-hinh-chung',
        'thoi-gian-lam-viec'   => 'thoi-gian-lam-viec',
        'lich-su-import'       => 'lich-su-import',

        'loai-khach-hang'      => 'loai-khach-hang',
        'khach-hang'           => 'khach-hang',
        'khach-hang-vang-lai'  => 'khach-hang-vang-lai',

        'nha-cung-cap'         => 'nha-cung-cap',
        'danh-muc-san-pham'    => 'danh-muc-san-pham',
        'don-vi-tinh'          => 'don-vi-tinh',
        'san-pham'             => 'san-pham',

        'phieu-nhap-kho'       => 'phieu-nhap-kho',
        'phieu-xuat-kho'       => 'phieu-xuat-kho',
        'quan-ly-ton-kho'      => 'quan-ly-ton-kho',
        'quan-ly-ban-hang'     => 'quan-ly-ban-hang',

        // Giao hàng: tách module riêng (đã chốt)
        'giao-hang'            => 'giao-hang',

        'cong-thuc-san-xuat'   => 'cong-thuc-san-xuat',
        'san-xuat'             => 'san-xuat',

        'phieu-thu'            => 'phieu-thu',
        'phieu-chi'            => 'phieu-chi',
        'thu-chi/bao-cao'      => 'bao-cao-thu-chi',
        'bao-cao-quan-tri'     => 'bao-cao-quan-tri',
    ];

    /** Public patterns (webhook/OAuth/options/template…) — tối thiểu & an toàn */
    /** Public patterns (webhook/OAuth/options/template…) — tối thiểu & an toàn */
    protected array $publicPrefixes = [
        // Webhook / OAuth (không qua jwt, public)
        'fb/webhook',                 // Webhook Facebook
        'zl/webhook',                 // Webhook Zalo
        'zl/oauth',                   // OAuth redirect/callback

        // AUTH: cho phép đọc thông tin phiên sau khi login (bỏ kiểm tra permission, vẫn đi qua jwt)
        'auth/me',
        'auth/logout',
        'auth/profile',
        'auth/change-password', 

        // Các endpoint tra cứu/khởi tạo cần mở (nếu chúng đi trong group có permission)
        'danh-sach-phan-quyen',
        'vt/references',
        'expense-categories',         // bao phủ .../parents, .../options, .../tree
    ];


    public function handle(Request $request, Closure $next): Response
    {
        // 0) Public tối thiểu
        if ($this->isPublic($request)) {
            return $next($request);
        }

        $user = Auth::user();

        // 1) Xác thực & bypass admin tuyệt đối
        if (!$user) {
            return CustomResponse::error('Chưa đăng nhập', Response::HTTP_UNAUTHORIZED);
        }
        if (strcasecmp($user->email, 'admin@gmail.com') === 0) {
            return $next($request); // bypass tuyệt đối
        }

        // 2) Lấy role & quyền
        $role = $user->vaiTro;
        if (!$role || $role->trang_thai != 1) {
            return CustomResponse::error('Vai trò không hợp lệ', Response::HTTP_UNAUTHORIZED);
        }
        $permissions = json_decode($role->phan_quyen ?? '[]', true) ?: [];

        // 3) Chuẩn hoá path & resolve module
        $path = ltrim(preg_replace('#^api/#', '', $request->path()), '/'); // bỏ "api/"
        $module = $this->resolveModule($path);

        if (!$module) {
            // Không resolve được module → từ chối (default-deny)
            return CustomResponse::error('Bạn không có quyền truy cập (module)', Response::HTTP_FORBIDDEN);
        }

        // 4) Resolve action (chuẩn + đặc thù)
        $action = $this->resolveAction($path, $request->method());
        if (!$action) {
            return CustomResponse::error('Bạn không có quyền truy cập (action)', Response::HTTP_FORBIDDEN);
        }

        // 5) So khớp với quyền trong vai trò (so sánh name == module, không contains)
$granted = false;
foreach ($permissions as $p) {
    if (!isset($p['name']) || $p['name'] !== $module) continue;
    $acts = $p['actions'] ?? [];

    if (!empty($acts[$action])) { $granted = true; break; }

    // Fallback: cho phép show khi có index
    if ($action === 'show' && !empty($acts['index'])) { $granted = true; break; }
}



        if (!$granted) {
            return CustomResponse::error('Bạn không có quyền thực hiện thao tác này', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /** Kiểm tra public tối thiểu */
    protected function isPublic(Request $request): bool
    {
        $p = ltrim(preg_replace('#^api/#', '', $request->path()), '/');
        foreach ($this->publicPrefixes as $pre) {
            if ($p === $pre || str_starts_with($p, $pre . '/')) {
                return true;
            }
        }
        return false;
    }

    /** Lấy segment đầu/đầu+1 rồi map alias → module canonical */
    protected function resolveModule(string $path): ?string
    {
        $seg = explode('/', $path);
        $first  = $seg[0] ?? '';
        $first2 = isset($seg[1]) ? $first . '/' . $seg[1] : $first;
        $first3 = isset($seg[2]) ? $first . '/' . $seg[1] . '/' . $seg[2] : $first2;

        // Ưu tiên match dài hơn trước
        foreach ([$first3, $first2, $first] as $key) {
            if (isset($this->alias[$key])) {
                return $this->alias[$key];
            }
        }
        return null;
    }

    /** Map HTTP + URL pattern -> action (chuẩn + đặc thù + export) */
    protected function resolveAction(string $path, string $method): ?string
    {
        // 1) Export / template
        if (preg_match('#(download-template-excel|kqkd-export|export)(\?|/|$)#', $path) === 1) {
            return 'export';
        }

        // 2) Đặc thù — Facebook
        if (preg_match('#^utilities/fb/conversations/\d+/reply$#', $path) === 1 && $method === 'POST') {
            return 'send';
        }
        if (preg_match('#^utilities/fb/conversations/\d+/assign$#', $path) === 1 && $method === 'POST') {
            return 'assign';
        }
        if (preg_match('#^utilities/fb/conversations/\d+/status$#', $path) === 1 && $method === 'PATCH') {
            return 'status';
        }

        // 3) Đặc thù — Zalo
        if (preg_match('#^utilities/zl/conversations/\d+/reply$#', $path) === 1 && $method === 'POST') {
            return 'send';
        }
        if (preg_match('#^utilities/zl/conversations/\d+/assign$#', $path) === 1 && $method === 'POST') {
            return 'assign';
        }
        if (preg_match('#^utilities/zl/conversations/\d+/status$#', $path) === 1 && $method === 'PATCH') {
            return 'status';
        }

        // 4) Đặc thù — CSKH Points
        if (preg_match('#^cskh(-points)?/events/\d+/send-zns$#', $path) === 1 && $method === 'POST') {
            return 'sendZns';
        }

        // 5) Đặc thù — Giao hàng: notify-and-set-status
        if (preg_match('#^giao-hang/\d+/notify-and-set-status$#', $path) === 1 && $method === 'POST') {
            return 'notifyAndSetStatus';
        }

        // 6) Đặc thù — KH vãng lai: convert
        if (preg_match('#^khach-hang-vang-lai/convert$#', $path) === 1 && $method === 'POST') {
            return 'convert';
        }

        // 7) Đặc thù — Cash internal transfers: post/unpost
        if (preg_match('#^cash/internal-transfers/\d+/post$#', $path) === 1 && $method === 'POST') {
            return 'post';
        }
        if (preg_match('#^cash/internal-transfers/\d+/unpost$#', $path) === 1 && $method === 'POST') {
            return 'unpost';
        }

                // 8) Đặc thù — Công nợ KH: xem chi tiết 1 khách
        if (preg_match('#^cong-no/customers/\d+$#', $path) === 1 && $method === 'GET') {
            return 'show';
        }


        // 8) Chuẩn CRUD
        $parts = explode('/', $path);
        $hasId = isset($parts[1]) && is_numeric($parts[1]);

        if (!$hasId) {
            // /prefix
            return match ($method) {
                'GET'  => 'index',
                'POST' => 'create',
                default => null,
            };
        } else {
            // /prefix/{id} (và các biến thể PUT/PATCH/DELETE)
            return match ($method) {
                'GET'           => 'show',
                'PUT', 'PATCH'  => 'edit',
                'DELETE'        => 'delete',
                default         => null,
            };
        }
    }
}
