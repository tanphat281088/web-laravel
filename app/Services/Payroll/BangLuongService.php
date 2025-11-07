<?php

namespace App\Services\Payroll;

use App\Models\BangCongThang;
use App\Models\LuongProfile;
use App\Models\LuongThang;
use Illuminate\Support\Facades\DB;
use App\Models\User;
/**
 * BangLuongService
 *
 * - Tính & upsert snapshot lương theo tháng (YYYY-MM).
 * - Tôn trọng locked=true (không ghi đè).
 * - Cho phép tính 1 user hoặc toàn bộ users.
 *
 * Quy ước:
 *  - Đơn vị tiền: VND (integer).
 *  - Tháng: chuỗi 'YYYY-MM'.
 *  - Công thức mặc định (có thể tùy chỉnh sau):
 *      luong_theo_cong = (luong_co_ban * he_so) * (so_ngay_cong / cong_chuan)
 *      bao_hiem_tinh_tren = (luong_co_ban * he_so)
 *      bhxh = round(bao_hiem_tinh_tren * pt_bhxh / 100)
 *      bhyt = round(bao_hiem_tinh_tren * pt_bhyt / 100)
 *      bhtn = round(bao_hiem_tinh_tren * pt_bhtn / 100)
 *      thuc_nhan = luong_theo_cong + phu_cap + thuong - phat - (bhxh+bhyt+bhtn) - khau_tru_khac - tam_ung
 *
 * - Nếu thiếu LuongProfile: dùng default an toàn.
 * - Nếu thiếu BangCongThang: cố gắng đọc 0 công; controller có thể "lazy-compute" bảng công trước khi gọi.
 */
class BangLuongService
{
    /** Default cấu hình khi user CHƯA có LuongProfile */
    private array $defaults = [
        'muc_luong_co_ban' => 0,
        'cong_chuan'       => 26,
        'he_so'            => 1.00,
        'phu_cap_mac_dinh' => 0,
        'pt_bhxh'          => 8.00,
        'pt_bhyt'          => 1.50,
        'pt_bhtn'          => 1.00,
    ];

    /**
     * Tính lương cho 1 người hoặc toàn bộ.
     * - $userId = null  => tất cả users có dòng BangCongThang của tháng đó.
     * - Tôn trọng locked=true (bỏ qua khi cập nhật).
     */
    public function computeMonth(string $thang, ?int $userId = null): void
    {
        // Lấy danh sách user cần tính, ưu tiên theo bảng công tháng.
        $uids = $this->pickUserIdsForMonth($thang, $userId);
        if (empty($uids)) return;

        foreach ($uids as $uid) {
            $this->computeOne($thang, (int) $uid);
        }
    }

    /**
     * Tính & upsert snapshot cho 1 user + tháng (an toàn).
     * - Không ghi đè nếu locked=true (giữ nguyên hàng hiện có).
     */
    public function computeOne(string $thang, int $userId): void
    {
        // 1) Lấy snapshot công (BangCongThang) của tháng
        $bc = BangCongThang::query()
            ->ofUser($userId)
            ->month($thang)
            ->first();

        // 2) Lấy profile lương (hoặc default)
        $profile = LuongProfile::query()->ofUser($userId)->first();

        $cfg = [
            'luong_co_ban' => (int) ($profile->muc_luong_co_ban ?? $this->defaults['muc_luong_co_ban']),
            'cong_chuan'   => (int) ($profile->cong_chuan       ?? $this->defaults['cong_chuan']),
            'he_so'        => (float)($profile->he_so            ?? $this->defaults['he_so']),
            'phu_cap_def'  => (int) ($profile->phu_cap_mac_dinh ?? $this->defaults['phu_cap_mac_dinh']),
            'pt_bhxh'      => (float)($profile->pt_bhxh          ?? $this->defaults['pt_bhxh']),
            'pt_bhyt'      => (float)($profile->pt_bhyt          ?? $this->defaults['pt_bhyt']),
            'pt_bhtn'      => (float)($profile->pt_bhtn          ?? $this->defaults['pt_bhtn']),
        ];

        // 3) Chỉ số công (nếu chưa có bảng công => 0)
        $soNgayCong = $bc ? (float) $bc->so_ngay_cong : 0.0;
        $soGioCong  = $bc ? (int)   $bc->so_gio_cong  : 0;

        // 4) Tính toán
        $baseXHeSo      = (int) round($cfg['luong_co_ban'] * $cfg['he_so']); // nền tính BH
        $congChuanSafe  = max(1, (int) $cfg['cong_chuan']);                  // tránh chia 0
        $luongTheoCong  = (int) round($baseXHeSo * ($soNgayCong / $congChuanSafe));

        $bhxh = (int) round($baseXHeSo * $cfg['pt_bhxh'] / 100);
        $bhyt = (int) round($baseXHeSo * $cfg['pt_bhyt'] / 100);
        $bhtn = (int) round($baseXHeSo * $cfg['pt_bhtn'] / 100);

        // 5) Upsert an toàn (bỏ qua dòng locked)
        DB::transaction(function () use (
            $thang, $userId, $cfg, $soNgayCong, $soGioCong, $luongTheoCong, $bhxh, $bhyt, $bhtn
        ) {
            /** @var LuongThang|null $row */
            $row = LuongThang::query()
                ->ofUser($userId)
                ->month($thang)
                ->lockForUpdate()
                ->first();

            if ($row && $row->locked) {
                // Tôn trọng locked: không ghi đè
                return;
            }

            // Nếu chưa có, tạo mới với mặc định phụ cấp từ profile
            if (!$row) {
                $row = new LuongThang();
                $row->user_id  = $userId;
                $row->thang    = $thang;
                $row->phu_cap  = (int) $cfg['phu_cap_def'];
                $row->thuong   = 0;
                $row->phat     = 0;
                $row->tam_ung  = 0;
                $row->khau_tru_khac = 0;
            }

            // Cập nhật snapshot
            $row->luong_co_ban    = (int) $cfg['luong_co_ban'];
            $row->cong_chuan      = (int) $cfg['cong_chuan'];
            $row->he_so           = (float)$cfg['he_so'];

            $row->so_ngay_cong    = (float)$soNgayCong;
            $row->so_gio_cong     = (int)  $soGioCong;

            $row->luong_theo_cong = (int)  $luongTheoCong;
            $row->bhxh            = (int)  $bhxh;
            $row->bhyt            = (int)  $bhyt;
            $row->bhtn            = (int)  $bhtn;

            // Tính thực nhận với các khoản cộng/trừ hiện có trên dòng
            $tongBH    = $row->bhxh + $row->bhyt + $row->bhtn;
            $thuNhapTruoc = $row->luong_theo_cong + $row->phu_cap + $row->thuong - $row->phat;
            $row->thuc_nhan = (int) max(0, $thuNhapTruoc - $tongBH - $row->khau_tru_khac - $row->tam_ung);

            $row->computed_at = now();
            $row->save();
        });
    }

    /**
     * Chọn danh sách user cần tính cho 1 tháng.
     * - Ưu tiên dựa trên bảng công tháng (ai có công/tháng đó).
     * - Nếu truyền userId => chỉ tính người đó, kể cả khi chưa có bảng công (sẽ ra 0 công).
     */
private function pickUserIdsForMonth(string $thang, ?int $userId = null): array
{
    if ($userId) return [(int) $userId];

    // Users ACTIVE (nếu có cột 'trang_thai'); nếu không có cột thì lấy tất cả users
    $active = User::query()
        ->when(\Schema::hasColumn('users', 'trang_thai'), fn($q) => $q->where('trang_thai', 1))
        ->pluck('id')->map(fn($v)=>(int)$v)->all();

    // Users có bảng công trong tháng
    $timesheet = \App\Models\BangCongThang::query()
        ->where('thang', $thang)
        ->pluck('user_id')->map(fn($v)=>(int)$v)->all();

    // Hợp nhất: ACTIVE ∪ CÓ BẢNG CÔNG
    return array_values(array_unique(array_merge($active, $timesheet)));
}


}
