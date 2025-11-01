<?php

namespace App\Services\Cash;

use Illuminate\Support\Facades\DB;

class CashLedgerService
{
    /**
     * Ghi bút toán sổ quỹ cho 1 Phiếu THU
     * - amount = so_tien của phiếu (dương = tiền vào, âm = hoàn/giảm)
     * - Ưu tiên dùng $phieuThu->tai_khoan_id; nếu trống thì map theo pttt/ngân_hàng/số_tk, cuối cùng fallback về UNKNOWN
     */
    public function recordReceipt(object $phieuThu): void
    {
        // Không ghi trùng cùng ref
        $exists = DB::table('so_quy_entries')
            ->where('ref_type', 'phieu_thu')
            ->where('ref_id',   $phieuThu->id)
            ->exists();

        if ($exists) {
            return;
        }

        $defaultCashId = $this->ensureDefaultCashAccount(); // TK tiền mặt mặc định
        $unknownId     = $this->ensureUnknownAccount();     // TK UNKNOWN

        // Ưu tiên id TK nếu phiếu đã có (vd: auto gán từ config/đơn hàng)
        $accId = !empty($phieuThu->tai_khoan_id)
            ? (int) $phieuThu->tai_khoan_id
            : $this->resolveAccountId(
                (int)($phieuThu->phuong_thuc_thanh_toan ?? 0),
                (string)($phieuThu->ngan_hang ?? ''),
                (string)($phieuThu->so_tai_khoan ?? ''),
                $defaultCashId,
                $unknownId
            );

        DB::table('so_quy_entries')->insert([
            'tai_khoan_id' => $accId,
            'ngay_ct'      => $this->toDateTime((string) ($phieuThu->ngay_thu ?? '')),
            'amount'       => (float) $phieuThu->so_tien,                 // + vào / − ra (nếu delta âm)
            'ref_type'     => 'phieu_thu',
            'ref_id'       => $phieuThu->id,
            'ref_code'     => (string) ($phieuThu->ma_phieu_thu ?? ''),
            'mo_ta'        => trim((string) ($phieuThu->ghi_chu ?? $phieuThu->ly_do_thu ?? '')),
            'created_by'   => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Xoá bút toán sổ quỹ gắn với 1 Phiếu THU
     */
    public function removeReceipt(object $phieuThu): void
    {
        DB::table('so_quy_entries')
            ->where('ref_type', 'phieu_thu')
            ->where('ref_id',   $phieuThu->id)
            ->delete();
    }

    /**
     * Ghi bút toán sổ quỹ cho 1 Phiếu CHI
     * - amount = - so_tien (tiền ra)
     * - Map tài khoản theo pt=1 (cash), pt=2 (bank/alias), khác/0 → UNKNOWN
     */
    public function recordPayment(object $phieuChi): void
    {
        // Tránh ghi trùng
        $exists = DB::table('so_quy_entries')
            ->where('ref_type', 'phieu_chi')
            ->where('ref_id',   $phieuChi->id)
            ->exists();

        if ($exists) {
            return;
        }

        $defaultCashId = $this->ensureDefaultCashAccount();
        $unknownId     = $this->ensureUnknownAccount();

$accId = !empty($phieuChi->tai_khoan_id)
    ? (int) $phieuChi->tai_khoan_id
    : $this->resolveAccountId(
        (int)($phieuChi->phuong_thuc_thanh_toan ?? 0),
        (string)($phieuChi->ngan_hang ?? ''),
        (string)($phieuChi->so_tai_khoan ?? ''),
        $defaultCashId,
        $unknownId
    );


        DB::table('so_quy_entries')->insert([
            'tai_khoan_id' => $accId,
            'ngay_ct'      => $this->toDateTime((string)($phieuChi->ngay_chi ?? '')),
            'amount'       => 0 - (float)$phieuChi->so_tien, // âm = tiền ra
            'ref_type'     => 'phieu_chi',
            'ref_id'       => $phieuChi->id,
            'ref_code'     => (string)($phieuChi->ma_phieu_chi ?? ''),
            'mo_ta'        => trim((string)($phieuChi->ghi_chu ?? '')),
            'created_by'   => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Gỡ bút toán sổ quỹ của 1 Phiếu CHI
     */
    public function removePayment(object $phieuChi): void
    {
        DB::table('so_quy_entries')
            ->where('ref_type', 'phieu_chi')
            ->where('ref_id',   $phieuChi->id)
            ->delete();
    }

    /**
     * POST chuyển nội bộ:
     * - Ghi 2 bút toán: (-so_tien) tại tài khoản nguồn, (+so_tien) tại tài khoản đích
     * - (Tuỳ chọn) nếu có phi_chuyen > 0: ghi thêm 1 bút toán (-phi_chuyen) tại tài khoản nguồn
     * - Idempotent theo (ref_type='chuyen_noi_bo'|'phi_chuyen', ref_id = trf.id)
     */
    public function postInternalTransfer(object $trf): void
    {
        DB::transaction(function () use ($trf) {
            // 1) Không ghi trùng
            $exists = DB::table('so_quy_entries')
                ->where('ref_type', 'chuyen_noi_bo')
                ->where('ref_id',   $trf->id)
                ->exists();

            if ($exists) {
                return;
            }

            // 2) Bút toán chuyển ra (TK nguồn)
            DB::table('so_quy_entries')->insert([
                'tai_khoan_id' => (int)$trf->tu_tai_khoan_id,
                'ngay_ct'      => $this->toDateTime((string)($trf->ngay_ct ?? '')),
                'amount'       => 0 - (float)$trf->so_tien,
                'ref_type'     => 'chuyen_noi_bo',
                'ref_id'       => $trf->id,
                'ref_code'     => (string)($trf->ma_phieu ?? ''),
                'mo_ta'        => trim((string)($trf->noi_dung ?? '')),
                'created_by'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // 3) Bút toán chuyển vào (TK đích)
            DB::table('so_quy_entries')->insert([
                'tai_khoan_id' => (int)$trf->den_tai_khoan_id,
                'ngay_ct'      => $this->toDateTime((string)($trf->ngay_ct ?? '')),
                'amount'       => (float)$trf->so_tien,
                'ref_type'     => 'chuyen_noi_bo',
                'ref_id'       => $trf->id,
                'ref_code'     => (string)($trf->ma_phieu ?? ''),
                'mo_ta'        => trim((string)($trf->noi_dung ?? '')),
                'created_by'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // 4) Bút toán phí chuyển (nếu có) tại TK nguồn
            $phi = (float)($trf->phi_chuyen ?? 0);
            if ($phi > 0) {
                DB::table('so_quy_entries')->insert([
                    'tai_khoan_id' => (int)$trf->tu_tai_khoan_id,
                    'ngay_ct'      => $this->toDateTime((string)($trf->ngay_ct ?? '')),
                    'amount'       => 0 - $phi,
                    'ref_type'     => 'phi_chuyen',
                    'ref_id'       => $trf->id,
                    'ref_code'     => (string)($trf->ma_phieu ?? ''),
                    'mo_ta'        => 'Phí chuyển nội bộ',
                    'created_by'   => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        });
    }

    /**
     * UNPOST chuyển nội bộ:
     * - Gỡ toàn bộ bút toán liên quan (chuyen_noi_bo + phi_chuyen) theo ref_id
     */
    public function unpostInternalTransfer(object $trf): void
    {
        DB::transaction(function () use ($trf) {
            DB::table('so_quy_entries')
                ->whereIn('ref_type', ['chuyen_noi_bo', 'phi_chuyen'])
                ->where('ref_id', $trf->id)
                ->delete();
        });
    }

    /* ===================== Helpers ===================== */

    /**
     * Map tài khoản theo quy tắc:
     *  - pt=1 → Tiền mặt (default cash)
     *  - pt=2 → tìm trực tiếp (ngân_hàng + số_tk) trong tai_khoan_tiens; nếu không có → dò alias; không được thì UNKNOWN
     *  - khác/0 → UNKNOWN
     */
    private function resolveAccountId(int $pt, string $bank, string $accno, int $defaultCashId, int $unknownId): int
    {
        $bank  = trim($bank);
        $accno = trim($accno);

        if ($pt === 1) {
            return $defaultCashId;
        }

        if ($pt === 2) {
            // 1) Match trực tiếp trong danh mục tài khoản
            $direct = DB::table('tai_khoan_tiens')
                ->when($bank !== '',  fn($q) => $q->where('ngan_hang', $bank))
                ->when($accno !== '', fn($q) => $q->where('so_tai_khoan', $accno))
                ->value('id');
            if ($direct) return (int)$direct;

            // 2) Thử alias (LIKE lỏng)
            $aliases = DB::table('tai_khoan_aliases')->where('is_active', 1)->get();
            foreach ($aliases as $al) {
                $ok = false;
                if ($al->pattern_bank && $bank) {
                    $ok = $ok || (stripos($bank, $al->pattern_bank) !== false);
                }
                if ($al->pattern_account && $accno) {
                    $ok = $ok || (stripos($accno, $al->pattern_account) !== false);
                }
                if ($al->pattern_note && ($bank || $accno)) {
                    $ok = $ok || (stripos(($bank . ' ' . $accno), $al->pattern_note) !== false);
                }
                if ($ok) return (int)$al->tai_khoan_id;
            }

            // 3) Không match được → UNKNOWN
            return $unknownId;
        }

        // pt khác chuẩn (0/null)
        return $unknownId;
    }

    private function ensureDefaultCashAccount(): int
    {
        $id = DB::table('tai_khoan_tiens')->where('is_default_cash', 1)->value('id');
        if ($id) return (int)$id;

        DB::table('tai_khoan_tiens')->insert([
            'ma_tk'           => 'CASH',
            'ten_tk'          => 'Tiền mặt',
            'loai'            => 'cash',
            'is_default_cash' => 1,
            'is_active'       => 1,
            'opening_balance' => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return (int) DB::table('tai_khoan_tiens')->where('is_default_cash', 1)->value('id');
    }

    private function ensureUnknownAccount(): int
    {
        $id = DB::table('tai_khoan_tiens')->where('ma_tk', 'UNKNOWN')->value('id');
        if ($id) return (int)$id;

        DB::table('tai_khoan_tiens')->insert([
            'ma_tk'           => 'UNKNOWN',
            'ten_tk'          => 'Chưa gắn tài khoản',
            'loai'            => 'bank',
            'is_default_cash' => 0,
            'is_active'       => 1,
            'opening_balance' => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return (int) DB::table('tai_khoan_tiens')->where('ma_tk', 'UNKNOWN')->value('id');
    }

    private function toDateTime(string $date): string
    {
        // Nếu FE gửi yyyy-mm-dd, ghép 00:00:00; nếu đã có time thì trả nguyên
        if (strlen($date) > 10) return $date;
        if ($date === '') return now()->format('Y-m-d H:i:s');
        return $date . ' 00:00:00';
    }
}
