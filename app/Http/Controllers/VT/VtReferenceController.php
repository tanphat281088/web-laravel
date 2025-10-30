<?php

namespace App\Http\Controllers\VT;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VtReferenceController extends Controller
{
    /**
     * GET /api/vt/references?ly_do=&q=&limit=
     * - ly_do: BAN | HUY | CHUYEN | KHAC (tuỳ chọn, chỉ dùng để ưu tiên gợi ý)
     * - q: chuỗi tìm kiếm (tự động match theo value/label)
     * - limit: số lượng trả về (mặc định 30)
     *
     * Trả về: [{ value, label, type }, ...]
     *  - type: PNVT | PXVT | DON_HANG
     *  - value: dùng để lưu vào tham_chieu (thường là số chứng từ/ mã đơn)
     */
    public function index(Request $request)
    {
        $lyDo  = strtoupper(trim((string) $request->get('ly_do', '')));
        $term  = trim((string) $request->get('q', ''));
        $limit = (int) ($request->get('limit', 30));
        if ($limit <= 0 || $limit > 200) $limit = 30;

        $options = [];

        // 1) PNVT — vt_receipts(so_ct, ngay_ct, ghi_chu)
        if (Schema::hasTable('vt_receipts') && Schema::hasColumn('vt_receipts', 'so_ct')) {
            $qr = DB::table('vt_receipts')
                ->select(['so_ct', 'ngay_ct', 'ghi_chu'])
                ->orderByDesc('ngay_ct')
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            foreach ($qr as $r) {
                $label = trim($r->so_ct . ' | ' . ($r->ngay_ct ?? '') . ($r->ghi_chu ? ' | ' . $r->ghi_chu : ''));
                $options[] = [
                    'value' => (string) $r->so_ct,
                    'label' => $label,
                    'type'  => 'PNVT',
                ];
            }
        }

        // 2) PXVT — vt_issues(so_ct, ngay_ct, ly_do, ghi_chu)
        if (Schema::hasTable('vt_issues') && Schema::hasColumn('vt_issues', 'so_ct')) {
            $qi = DB::table('vt_issues')
                ->select(['so_ct', 'ngay_ct', 'ly_do', 'ghi_chu'])
                ->orderByDesc('ngay_ct')
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            foreach ($qi as $r) {
                $label = trim($r->so_ct . ' | ' . ($r->ngay_ct ?? '') . ' | ' . ($r->ly_do ?? '') . ($r->ghi_chu ? ' | ' . $r->ghi_chu : ''));
                $options[] = [
                    'value' => (string) $r->so_ct,
                    'label' => $label,
                    'type'  => 'PXVT',
                ];
            }
        }

        // 3) Đơn hàng (nếu tồn tại) — don_hangs(ma_don_hang | so_hieu | id)
        if (Schema::hasTable('don_hangs')) {
            // dò cột mã khả dụng
            $codeCols = array_filter(['ma_don_hang', 'so_hieu', 'code'], fn($c) => Schema::hasColumn('don_hangs', $c));
            $nameCol  = Schema::hasColumn('don_hangs', 'ten_khach_hang') ? 'ten_khach_hang' : (Schema::hasColumn('don_hangs', 'ten_nguoi_nhan') ? 'ten_nguoi_nhan' : null);
            $dateCol  = Schema::hasColumn('don_hangs', 'ngay_dat') ? 'ngay_dat' : (Schema::hasColumn('don_hangs', 'created_at') ? 'created_at' : null);

            if (!empty($codeCols)) {
                $select = ['id'];
                foreach ($codeCols as $c) $select[] = $c;
                if ($nameCol) $select[] = $nameCol;
                if ($dateCol) $select[] = $dateCol;

                $qd = DB::table('don_hangs')
                    ->select($select)
                    ->orderByDesc($dateCol ?? 'id')
                    ->limit(200)
                    ->get();

                foreach ($qd as $r) {
                    // lấy code đầu tiên có giá trị
                    $code = null;
                    foreach ($codeCols as $c) {
                        if (!empty($r->{$c})) { $code = (string) $r->{$c}; break; }
                    }
                    if (!$code) $code = 'DH-' . (string) $r->id;

                    $parts = [$code];
                    if ($dateCol && !empty($r->{$dateCol})) $parts[] = (string) $r->{$dateCol};
                    if ($nameCol && !empty($r->{$nameCol})) $parts[] = (string) $r->{$nameCol};

                    $options[] = [
                        'value' => $code,
                        'label' => implode(' | ', $parts),
                        'type'  => 'DON_HANG',
                    ];
                }
            }
        }

        // Lọc theo ly_do (ưu tiên sắp xếp)
        if ($lyDo === 'BAN') {
            // ưu tiên DON_HANG, sau đó PXVT, PNVT
            usort($options, function ($a, $b) {
                $rank = ['DON_HANG' => 0, 'PXVT' => 1, 'PNVT' => 2];
                return ($rank[$a['type']] ?? 9) <=> ($rank[$b['type']] ?? 9);
            });
        } elseif ($lyDo === 'CHUYEN') {
            // ưu tiên PXVT trước (chứng từ xuất chuyển)
            usort($options, function ($a, $b) {
                $rank = ['PXVT' => 0, 'PNVT' => 1, 'DON_HANG' => 2];
                return ($rank[$a['type']] ?? 9) <=> ($rank[$b['type']] ?? 9);
            });
        } else {
            // mặc định: PNVT & PXVT trước, DON_HANG sau
            usort($options, function ($a, $b) {
                $rank = ['PNVT' => 0, 'PXVT' => 1, 'DON_HANG' => 2];
                return ($rank[$a['type']] ?? 9) <=> ($rank[$b['type']] ?? 9);
            });
        }

        // Tìm kiếm theo q (trong label/value)
        if ($term !== '') {
            $needle = mb_strtolower($term);
            $options = array_values(array_filter($options, function ($opt) use ($needle) {
                $hay = mb_strtolower(($opt['value'] ?? '') . ' ' . ($opt['label'] ?? ''));
                return str_contains($hay, $needle);
            }));
        }

        // Giới hạn số lượng trả về
        if (count($options) > $limit) {
            $options = array_slice($options, 0, $limit);
        }

        return CustomResponse::success($options);
    }
}
