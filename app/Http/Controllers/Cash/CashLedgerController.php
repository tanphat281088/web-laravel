<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Models\SoQuyEntry;
use App\Models\TaiKhoanTien;
use App\Class\CustomResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashLedgerController extends Controller
{
    // GET /cash/ledger
    public function ledger(Request $request)
    {
        $q = SoQuyEntry::query()->with('taiKhoan:id,ten_tk,loai,ngan_hang,so_tai_khoan');

        // Filters
        if ($request->filled('tai_khoan_id')) {
            $q->where('tai_khoan_id', (int)$request->get('tai_khoan_id'));
        }
        if ($request->filled('from')) {
            $q->where('ngay_ct', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $q->where('ngay_ct', '<=', $request->get('to'));
        }
        if ($request->filled('ref_type')) {
            $q->where('ref_type', $request->get('ref_type'));
        }
        if ($keyword = trim((string)$request->get('keyword', ''))) {
            $q->where(function($qq) use ($keyword) {
                $qq->where('ref_code', 'LIKE', "%{$keyword}%")
                   ->orWhere('mo_ta', 'LIKE', "%{$keyword}%");
            });
        }
        if ($request->filled('reconciled')) {
            $yes = (int)$request->get('reconciled') === 1;
            $yes ? $q->whereNotNull('reconciled_at') : $q->whereNull('reconciled_at');
        }

        // Sort mặc định: thời gian gần nhất trước
        $q->orderBy('ngay_ct', 'desc')->orderBy('id', 'desc');

        // Phân trang nhẹ (mặc định 25)
        $per  = max(1, (int)($request->get('per_page', 25)));
        $page = max(1, (int)($request->get('page', 1)));
        $rows = $q->paginate($per, ['*'], 'page', $page);

        return CustomResponse::success([
            'collection' => $rows->items(),
            'total'      => $rows->total(),
            'pagination' => [
                'current_page'  => $rows->currentPage(),
                'last_page'     => $rows->lastPage(),
                'from'          => $rows->firstItem(),
                'to'            => $rows->lastItem(),
                'total_current' => count($rows->items()),
            ],
        ]);
    }

    // GET /cash/balances  → trả về breakdown Opening/In/Out/Net/Ending theo từng tài khoản
    public function balances(Request $request)
    {
        $from = $request->get('from'); // yyyy-mm-dd or yyyy-mm-dd HH:MM:SS
        $to   = $request->get('to');

        // Lấy danh sách tài khoản đang active (hoặc tất cả nếu request 'all=1')
        $accounts = TaiKhoanTien::query()
            ->when((int)$request->get('all', 0) !== 1, fn($qq) => $qq->where('is_active', true))
            ->orderBy('loai')->orderBy('ten_tk')
            ->get(['id','ten_tk','loai','ngan_hang','so_tai_khoan','opening_balance','opening_date']);

        // Tính Opening: opening_balance + sum(amount BEFORE from)
        $opening = collect();
        if ($from) {
            $opening = SoQuyEntry::query()
                ->select('tai_khoan_id', DB::raw('SUM(amount) as sum_amount'))
                ->where('ngay_ct', '<', $from)
                ->groupBy('tai_khoan_id')
                ->pluck('sum_amount', 'tai_khoan_id');
        }

        // In/Out/Net trong kỳ [from..to]
        $period = SoQuyEntry::query()
            ->select('tai_khoan_id',
                DB::raw('SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END) as total_in'),
                DB::raw('SUM(CASE WHEN amount <  0 THEN -amount ELSE 0 END) as total_out'),
                DB::raw('SUM(amount) as net'))
            ->when($from, fn($qq) => $qq->where('ngay_ct', '>=', $from))
            ->when($to,   fn($qq) => $qq->where('ngay_ct', '<=', $to))
            ->groupBy('tai_khoan_id')
            ->get()
            ->keyBy('tai_khoan_id');

        $rows = $accounts->map(function($acc) use ($opening, $period) {
            $openBal = (float)($acc->opening_balance ?? 0);

            // cộng lũy kế trước kỳ
            $openBal += (float)$opening->get($acc->id, 0);

            $in   = (float)($period->get($acc->id)->total_in ?? 0);
            $out  = (float)($period->get($acc->id)->total_out ?? 0);
            $net  = (float)($period->get($acc->id)->net ?? 0);
            $end  = $openBal + $net;

            return [
                'tai_khoan_id' => $acc->id,
                'ten_tk'       => $acc->ten_tk,
                'loai'         => $acc->loai,
                'ngan_hang'    => $acc->ngan_hang,
                'so_tai_khoan' => $acc->so_tai_khoan,
                'opening'      => round($openBal, 2),
                'in'           => round($in, 2),
                'out'          => round($out, 2),
                'net'          => round($net, 2),
                'ending'       => round($end, 2),
            ];
        });

        return CustomResponse::success([
            'collection' => $rows,
            'total'      => $rows->count(),
        ]);
    }

    // GET /cash/balances/summary  → dữ liệu gọn cho dashboard (card theo tài khoản)
    public function summary(Request $request)
    {
        // Tận dụng /balances và chỉ trả về mảng rút gọn
        $request2 = $request->merge(['all' => $request->get('all', 0)]);
        $resp = $this->balances($request2);
        $data = $resp->getData(true);

        // Chuẩn hóa cấu trúc (giữ nguyên order)
        $cards = collect($data['data']['collection'] ?? [])->map(function ($r) {
            return [
                'tai_khoan_id' => $r['tai_khoan_id'],
                'label'        => $r['ten_tk'],
                'opening'      => $r['opening'],
                'in'           => $r['in'],
                'out'          => $r['out'],
                'net'          => $r['net'],
                'ending'       => $r['ending'],
                'meta'         => [
                    'loai'         => $r['loai'],
                    'ngan_hang'    => $r['ngan_hang'] ?? null,
                    'so_tai_khoan' => $r['so_tai_khoan'] ?? null,
                ],
            ];
        })->values();

        return CustomResponse::success($cards);
    }
}
