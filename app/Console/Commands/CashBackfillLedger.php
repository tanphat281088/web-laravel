<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CashBackfillLedger extends Command
{
    protected $signature = 'cash:backfill-ledger
                            {--from= : Ngày bắt đầu (YYYY-MM-DD)}
                            {--to=   : Ngày kết thúc  (YYYY-MM-DD)}
                            {--dry-run : Chỉ mô phỏng, không ghi DB}
                            {--reset   : Xóa các bút toán đã sinh trong khoảng thời gian rồi backfill lại}';

    protected $description = 'Backfill so_quy_entries từ lịch sử phieu_thus và phieu_chis theo khoảng ngày.';

    public function handle(): int
    {
        $from = $this->option('from');
        $to   = $this->option('to');
        $dry  = (bool) $this->option('dry-run');
        $reset= (bool) $this->option('reset');

        // 1) Đảm bảo tồn tại tài khoản "UNKNOWN" để hứng các giao dịch CK chưa map được
        $unknownId = $this->ensureUnknownAccount();

        // 2) Tải các tài khoản & alias vào bộ nhớ (để map nhanh)
        $accounts = DB::table('tai_khoan_tiens')->get()->keyBy('id');
        $defaultCashId = (int) DB::table('tai_khoan_tiens')->where('is_default_cash', 1)->value('id');
        if (! $defaultCashId) {
            $this->warn('⚠️ Chưa có tài khoản Tiền mặt (is_default_cash=1). Sẽ cố gắng tạo tạm.');
            $defaultCashId = $this->ensureDefaultCashAccount();
            // reload
            $accounts = DB::table('tai_khoan_tiens')->get()->keyBy('id');
        }

        $aliases = DB::table('tai_khoan_aliases')->where('is_active', 1)->get();

        // 3) (Tuỳ chọn) reset các bút toán trong khoảng thời gian
        if ($reset && ! $dry) {
            $this->info('⟲ Reset bút toán sổ quỹ trong khoảng thời gian yêu cầu...');
            DB::table('so_quy_entries')
                ->when($from, fn($q) => $q->where('ngay_ct', '>=', $from))
                ->when($to,   fn($q) => $q->where('ngay_ct', '<=', $to))
                ->delete();
        }

        // 4) Backfill Phiếu THU → amount dương
        $this->section('Phiếu THU');
        $thuQuery = DB::table('phieu_thus')
            ->select('id','ma_phieu_thu','ngay_thu','so_tien','phuong_thuc_thanh_toan','ngan_hang','so_tai_khoan','ghi_chu')
            ->when($from, fn($q) => $q->where('ngay_thu', '>=', $from))
            ->when($to,   fn($q) => $q->where('ngay_thu', '<=', $to))
            ->orderBy('id');

        $countThu = (clone $thuQuery)->count();
        $this->line("  • Số phiếu thu trong kỳ: {$countThu}");

        $processedThu = 0;
        $thuQuery->chunkById(500, function ($rows) use (&$processedThu, $dry, $defaultCashId, $unknownId, $aliases, $accounts) {
            foreach ($rows as $r) {
                $accId = $this->resolveAccountId(
                    (int)($r->phuong_thuc_thanh_toan ?? 0),
                    $r->ngan_hang,
                    $r->so_tai_khoan,
                    $aliases,
                    $accounts,
                    $defaultCashId,
                    $unknownId
                );

                // Idempotent: tránh ghi trùng nếu đã tồn tại bút toán cùng ref
                $exists = DB::table('so_quy_entries')
                    ->where('ref_type', 'phieu_thu')
                    ->where('ref_id',   $r->id)
                    ->exists();

                if (! $exists) {
                    $payload = [
                        'tai_khoan_id' => $accId,
                        'ngay_ct'      => $r->ngay_thu . ' 00:00:00',
                        'amount'       => (float)$r->so_tien, // dương
                        'ref_type'     => 'phieu_thu',
                        'ref_id'       => $r->id,
                        'ref_code'     => $r->ma_phieu_thu,
                        'mo_ta'        => trim((string)($r->ghi_chu ?? '')),
                        'created_by'   => null,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];

                    if ($dry) {
                        $this->line("    [DRY] +THU  PT={$r->ma_phieu_thu}  TK={$accId}  SOTIEN={$r->so_tien}");
                    } else {
                        DB::table('so_quy_entries')->insert($payload);
                    }
                }

                $processedThu++;
            }
        });

        $this->line("  ✓ Đã xử lý: {$processedThu} phiếu thu");

        // 5) Backfill Phiếu CHI → amount âm
        $this->section('Phiếu CHI');
        $chiQuery = DB::table('phieu_chis')
            ->select('id','ma_phieu_chi','ngay_chi','so_tien','phuong_thuc_thanh_toan','ngan_hang','so_tai_khoan','ghi_chu')
            ->when($from, fn($q) => $q->where('ngay_chi', '>=', $from))
            ->when($to,   fn($q) => $q->where('ngay_chi', '<=', $to))
            ->orderBy('id');

        $countChi = (clone $chiQuery)->count();
        $this->line("  • Số phiếu chi trong kỳ: {$countChi}");

        $processedChi = 0;
        $chiQuery->chunkById(500, function ($rows) use (&$processedChi, $dry, $defaultCashId, $unknownId, $aliases, $accounts) {
            foreach ($rows as $r) {
                $accId = $this->resolveAccountId(
                    (int)($r->phuong_thuc_thanh_toan ?? 0),
                    $r->ngan_hang,
                    $r->so_tai_khoan,
                    $aliases,
                    $accounts,
                    $defaultCashId,
                    $unknownId
                );

                $exists = DB::table('so_quy_entries')
                    ->where('ref_type', 'phieu_chi')
                    ->where('ref_id',   $r->id)
                    ->exists();

                if (! $exists) {
                    $payload = [
                        'tai_khoan_id' => $accId,
                        'ngay_ct'      => $r->ngay_chi . ' 00:00:00',
                        'amount'       => 0 - (float)$r->so_tien, // âm
                        'ref_type'     => 'phieu_chi',
                        'ref_id'       => $r->id,
                        'ref_code'     => $r->ma_phieu_chi,
                        'mo_ta'        => trim((string)($r->ghi_chu ?? '')),
                        'created_by'   => null,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];

                    if ($dry) {
                        $this->line("    [DRY] -CHI  PC={$r->ma_phieu_chi}  TK={$accId}  SOTIEN={$r->so_tien}");
                    } else {
                        DB::table('so_quy_entries')->insert($payload);
                    }
                }

                $processedChi++;
            }
        });

        $this->line("  ✓ Đã xử lý: {$processedChi} phiếu chi");

        $this->info('Hoàn tất backfill.');
        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->info("=== {$title} ===");
    }

    /**
     * Map tài khoản theo quy tắc:
     *  - pt=1 → Tiền mặt (default cash)
     *  - pt=2 → match trực tiếp theo (ngan_hang + so_tai_khoan) trong tai_khoan_tiens; nếu không có, thử alias; cuối cùng → UNKNOWN
     *  - khác/0 → UNKNOWN
     */
    private function resolveAccountId(int $pt, ?string $bank, ?string $accno, $aliases, $accounts, int $defaultCashId, int $unknownId): int
    {
        $bank  = trim((string)($bank ?? ''));
        $accno = trim((string)($accno ?? ''));

        if ($pt === 1) {
            return $defaultCashId;
        }

        if ($pt === 2) {
            // 1) Thử match trực tiếp trong danh mục tài khoản
            $direct = DB::table('tai_khoan_tiens')
                ->when($bank,  fn($q) => $q->where('ngan_hang', $bank))
                ->when($accno, fn($q) => $q->where('so_tai_khoan', $accno))
                ->value('id');

            if ($direct) return (int)$direct;

            // 2) Thử theo alias (LIKE lỏng)
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

                if ($ok) {
                    return (int)$al->tai_khoan_id;
                }
            }

            // 3) Không match được → UNKNOWN
            return $unknownId;
        }

        // Trường hợp pt=0/null khác chuẩn
        return $unknownId;
    }

    private function ensureDefaultCashAccount(): int
    {
        // Tạo nếu chưa có
        $exists = DB::table('tai_khoan_tiens')->where('is_default_cash', 1)->exists();
        if (! $exists) {
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
        }
        return (int) DB::table('tai_khoan_tiens')->where('is_default_cash', 1)->value('id');
    }

    private function ensureUnknownAccount(): int
    {
        // Tài khoản hứng giao dịch CK chưa map được (an toàn, add-only)
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
}
