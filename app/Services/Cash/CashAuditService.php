<?php

namespace App\Services\Cash;

use Illuminate\Support\Facades\DB;

class CashAuditService
{
    /**
     * Chuẩn hoá tên ngân hàng: trim + upper (bao dung khi so sánh LIKE)
     */
    protected function normalizeBank(?string $bank): ?string
    {
        if ($bank === null) return null;
        $bank = trim($bank);
        return $bank === '' ? null : mb_strtoupper($bank, 'UTF-8');
    }

    /**
     * Chuẩn hoá số tài khoản: bỏ khoảng trắng & dấu gạch
     */
    protected function normalizeAcc(?string $acc): ?string
    {
        if ($acc === null) return null;
        $acc = preg_replace('/[ -]/', '', trim($acc));
        return $acc === '' ? null : $acc;
    }

    /**
     * Lấy thông tin bank/acc từ tài khoản tiền theo id
     */
    protected function getAccountAlias(int $taiKhoanId): array
    {
        $row = DB::table('tai_khoan_tiens')
            ->select('ngan_hang', 'so_tai_khoan')
            ->where('id', $taiKhoanId)
            ->first();

        return [
            'bank' => $this->normalizeBank($row->ngan_hang ?? null),
            'acc'  => $this->normalizeAcc($row->so_tai_khoan ?? null),
        ];
    }

    /**
     * Tạo query tập PHIẾU THU CK theo bank/acc, có bao dung format (LIKE cho bank, acc chuẩn hoá)
     */
    protected function buildReceiptSetQuery(?string $from, ?string $to, ?string $bankNorm, ?string $accNorm)
    {
        $q = DB::table('phieu_thus as pt')
            ->select('pt.id', 'pt.ma_phieu_thu', 'pt.ngay_thu', 'pt.so_tien')
            ->where('pt.phuong_thuc_thanh_toan', 2);

        if ($from) $q->where('pt.ngay_thu', '>=', $from);
        if ($to)   $q->where('pt.ngay_thu', '<=', $to);

        if ($bankNorm) {
            $q->whereRaw("UPPER(TRIM(COALESCE(pt.ngan_hang,''))) LIKE ?", ["%{$bankNorm}%"]);
        }
        if ($accNorm) {
            $q->whereRaw("REPLACE(REPLACE(COALESCE(pt.so_tai_khoan,''),' ',''),'-','') = ?", [$accNorm]);
        }

        return $q;
    }

    /**
     * Audit delta cho PHIẾU THU CK theo alias (bank/account) của tài khoản nhận.
     *
     * @param  int         $taiKhoanId
     * @param  string|null $aliasBank
     * @param  string|null $aliasAccount
     * @param  string|null $from         Y-m-d
     * @param  string|null $to           Y-m-d
     * @param  bool        $debug        Nếu true, trả thêm totals.meta.debug (top10, include_ok)
     * @param  bool        $includeOk    Nếu true, top10 có thể gồm cả dòng Δ = 0
     *
     * @return array{
     *   missing: array<int, array{id:int,ma_phieu_thu:string,ngay_thu:string,expected:float,ledger_sum:float,delta:float}>,
     *   over:    array<int, array{id:int,ma_phieu_thu:string,ngay_thu:string,expected:float,ledger_sum:float,delta:float}>,
     *   totals:  array{
     *     tong_thieu: float,
     *     tong_du:    float,
     *     net:        float,
     *     meta:       array{
     *       tai_khoan_id:int,
     *       alias_bank:   ?string,
     *       alias_acc:    ?string,
     *       from:         ?string,
     *       to:           ?string,
     *       matched_count:int,
     *       expected_sum: float,
     *       ledger_sum:   float,
     *       debug?:       array{
     *         include_ok: int,
     *         top10: array<int, array{id:int,ma_phieu_thu:string,ngay_thu:string,expected:float,ledger_sum:float,delta:float}>
     *       }
     *     }
     *   }
     * }
     */
    public function auditReceiptsDelta(
        int $taiKhoanId,
        ?string $aliasBank = null,
        ?string $aliasAccount = null,
        ?string $from = null,
        ?string $to = null,
        bool $debug = false,
        bool $includeOk = false
    ): array {
        // Bank/acc chuẩn hoá: ưu tiên alias truyền vào; nếu rỗng lấy từ tai_khoan_tiens
        $bankNorm = $this->normalizeBank($aliasBank);
        $accNorm  = $this->normalizeAcc($aliasAccount);
        if (!$bankNorm || !$accNorm) {
            $alias = $this->getAccountAlias($taiKhoanId);
            $bankNorm = $bankNorm ?: $alias['bank'];
            $accNorm  = $accNorm  ?: $alias['acc'];
        }

        // Tập ref_id phiếu thu CK match điều kiện
        $ids = $this->buildReceiptSetQuery($from, $to, $bankNorm, $accNorm)->get();

        // Chuẩn bị biến thống kê & tập hợp top
        $expectedTotal = 0.0; // tổng trên phiếu
        $ledgerTotal   = 0.0; // tổng trong sổ quỹ (cùng ref)
        $allRows       = [];  // để sort lấy top10 (tuỳ includeOk)

        if ($ids->isEmpty()) {
            // Trả meta cơ bản cả khi rỗng
            $metaBase = [
                'tai_khoan_id' => $taiKhoanId,
                'alias_bank'   => $bankNorm,
                'alias_acc'    => $accNorm,
                'from'         => $from,
                'to'           => $to,
                'matched_count'=> 0,
                'expected_sum' => 0.0,
                'ledger_sum'   => 0.0,
            ];

            $totals = [
                'tong_thieu' => 0.0,
                'tong_du'    => 0.0,
                'net'        => 0.0,
                'meta'       => $metaBase,
            ];

            if ($debug) {
                $totals['meta']['debug'] = [
                    'include_ok' => $includeOk ? 1 : 0,
                    'top10'      => [],
                ];
            }

            return [
                'missing' => [],
                'over'    => [],
                'totals'  => $totals,
            ];
        }

        // Ledger sum theo ref_id (trên MỌI tài khoản)
        $ledgerSum = DB::table('so_quy_entries')
            ->select('ref_id', DB::raw('SUM(amount) as sum_amount'))
            ->where('ref_type', 'phieu_thu')
            ->when($from, fn($q) => $q->where('ngay_ct', '>=', "{$from} 00:00:00"))
            ->when($to,   fn($q) => $q->where('ngay_ct', '<=', "{$to} 23:59:59"))
            ->whereIn('ref_id', $ids->pluck('id'))
            ->groupBy('ref_id')
            ->pluck('sum_amount', 'ref_id');

        $missing    = [];
        $over       = [];
        $sumMissing = 0.0;
        $sumOver    = 0.0;

        foreach ($ids as $r) {
            $sum   = (float)($ledgerSum[$r->id] ?? 0.0);
            $delta = (float)$r->so_tien - $sum;

            $row = [
                'id'           => (int)$r->id,
                'ma_phieu_thu' => (string)$r->ma_phieu_thu,
                'ngay_thu'     => (string)$r->ngay_thu,
                'expected'     => (float)$r->so_tien,
                'ledger_sum'   => $sum,
                'delta'        => $delta,
            ];

            $expectedTotal += (float)$r->so_tien;
            $ledgerTotal   += $sum;
            $allRows[]      = $row;

            if ($delta > 0) { // thiếu
                $missing[]    = $row;
                $sumMissing  += $delta;
            } elseif ($delta < 0) { // dư
                $over[]       = $row;
                $sumOver     += $delta; // âm
            }
        }

        // Meta cơ bản
        $metaBase = [
            'tai_khoan_id' => $taiKhoanId,
            'alias_bank'   => $bankNorm,
            'alias_acc'    => $accNorm,
            'from'         => $from,
            'to'           => $to,
            'matched_count'=> $ids->count(),
            'expected_sum' => round($expectedTotal, 2),
            'ledger_sum'   => round($ledgerTotal, 2),
        ];

        // Xây top10 theo |delta|
        $topSrc = $includeOk
            ? $allRows
            : array_values(array_filter($allRows, fn($x) => ($x['delta'] ?? 0.0) != 0.0));

        usort($topSrc, fn($a, $b) => abs($b['delta'] ?? 0.0) <=> abs($a['delta'] ?? 0.0));
        $top10 = array_slice($topSrc, 0, 10);

        // Kết quả
        $out = [
            'missing' => $missing,
            'over'    => $over,
            'totals'  => [
                'tong_thieu' => round($sumMissing, 2),
                'tong_du'    => round($sumOver, 2),
                'net'        => round($sumMissing + $sumOver, 2),
                'meta'       => $metaBase,
            ],
        ];

        if ($debug) {
            $out['totals']['meta']['debug'] = [
                'include_ok' => $includeOk ? 1 : 0,
                'top10'      => $top10,
            ];
        }

        return $out;
    }

    /**
     * Áp dụng sửa (idempotent): backfill thiếu (delta>0) và/hoặc điều chỉnh âm (delta<0).
     * scope: 'missing' | 'over' | 'both'
     *
     * @return array{rows_missing:int, rows_over:int, sum_missing:float, sum_over:float, net:float, dry_run:int}
     */
    public function applyFix(
        int $taiKhoanId,
        ?string $aliasBank = null,
        ?string $aliasAccount = null,
        ?string $from = null,
        ?string $to = null,
        string $scope = 'both',
        bool $dryRun = false,
        ?int $userId = null
    ): array {
        // gọi audit với debug=false, includeOk=false để lấy đúng tập lệch
        $audit   = $this->auditReceiptsDelta($taiKhoanId, $aliasBank, $aliasAccount, $from, $to, false, false);
        $missing = $audit['missing'] ?? [];
        $over    = $audit['over'] ?? [];

        $doMissing = in_array($scope, ['missing', 'both'], true);
        $doOver    = in_array($scope, ['over', 'both'], true);

        $rowsMissing = $doMissing ? count($missing) : 0;
        $rowsOver    = $doOver ? count($over) : 0;
        $sumMissing  = $doMissing ? array_sum(array_column($missing, 'delta')) : 0.0;
        $sumOver     = $doOver ? array_sum(array_column($over, 'delta')) : 0.0; // âm
        $net         = $sumMissing + $sumOver;

        if ($dryRun || (!$doMissing && !$doOver) || ($rowsMissing + $rowsOver) === 0) {
            return [
                'rows_missing' => $rowsMissing,
                'rows_over'    => $rowsOver,
                'sum_missing'  => round($sumMissing, 2),
                'sum_over'     => round($sumOver, 2),
                'net'          => round($net, 2),
                'dry_run'      => 1,
            ];
        }

        DB::transaction(function () use ($taiKhoanId, $doMissing, $doOver, $missing, $over, $userId) {
            $now = now();

            // Backfill thiếu (delta > 0)
            if ($doMissing && !empty($missing)) {
                $inserts = [];
                foreach ($missing as $r) {
                    if (($r['delta'] ?? 0) > 0) {
                        $inserts[] = [
                            'tai_khoan_id' => $taiKhoanId,
                            'ngay_ct'      => $r['ngay_thu'] . ' 00:00:00',
                            'amount'       => (float)$r['delta'], // dương
                            'ref_type'     => 'phieu_thu',
                            'ref_id'       => (int)$r['id'],
                            'ref_code'     => (string)$r['ma_phieu_thu'],
                            'mo_ta'        => 'auto backfill delta',
                            'created_by'   => $userId,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ];
                    }
                }
                if (!empty($inserts)) {
                    DB::table('so_quy_entries')->insert($inserts);
                }
            }

            // Điều chỉnh âm (delta < 0)
            if ($doOver && !empty($over)) {
                $inserts = [];
                foreach ($over as $r) {
                    if (($r['delta'] ?? 0) < 0) {
                        $inserts[] = [
                            'tai_khoan_id' => $taiKhoanId,
                            'ngay_ct'      => $r['ngay_thu'] . ' 00:00:00',
                            'amount'       => (float)$r['delta'], // âm
                            'ref_type'     => 'phieu_thu',
                            'ref_id'       => (int)$r['id'],
                            'ref_code'     => (string)$r['ma_phieu_thu'],
                            'mo_ta'        => 'auto adjust negative delta',
                            'created_by'   => $userId,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ];
                    }
                }
                if (!empty($inserts)) {
                    DB::table('so_quy_entries')->insert($inserts);
                }
            }

            // (Tuỳ chọn) Ghi log run tổng hợp vào cash_audit_runs (nếu muốn)
            // DB::table('cash_audit_runs')->insert([...]);
        });

        return [
            'rows_missing' => $rowsMissing,
            'rows_over'    => $rowsOver,
            'sum_missing'  => round($sumMissing, 2),
            'sum_over'     => round($sumOver, 2),
            'net'          => round($net, 2),
            'dry_run'      => 0,
        ];
    }
}
