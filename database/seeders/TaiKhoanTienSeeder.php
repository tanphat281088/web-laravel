<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaiKhoanTienSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // -------------------------------
        // 1) Danh mục tài khoản (UPSERT)
        //    Sử dụng đúng các cột có trong bảng:
        //    ma_tk, ten_tk, loai, so_tai_khoan, ngan_hang,
        //    is_default_cash, is_active, opening_balance, opening_date,
        //    ghi_chu, created_at, updated_at
        // -------------------------------
        $accounts = [
            [
                'ma_tk'            => 'CASH',
                'ten_tk'           => 'Tiền mặt',
                'loai'             => 'cash',
                'so_tai_khoan'     => null,
                'ngan_hang'        => null,
                'is_default_cash'  => true,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            // CÔNG TY CỔ PHẦN TRANG TRÍ PHÁT HOÀNG GIA – 87896789 – TECHCOMBANK
            [
                'ma_tk'            => 'COMPANY',
                'ten_tk'           => 'CÔNG TY CỔ PHẦN TRANG TRÍ PHÁT HOÀNG GIA',
                'loai'             => 'bank',
                'so_tai_khoan'     => '87896789',
                'ngan_hang'        => 'TCB', // Techcombank
                'is_default_cash'  => false,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => 'Techcombank',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            // ZaloPay (ví dụ)
            [
                'ma_tk'            => 'ZLP',
                'ten_tk'           => 'ZaloPay',
                'loai'             => 'ewallet',
                'so_tai_khoan'     => 'ZLP-0000', // thay khi có số thật
                'ngan_hang'        => 'ZaloPay',
                'is_default_cash'  => false,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            // TRẦN TẤN PHÁT – 2810 1988 8888 88 – TECHCOMBANK
            [
                'ma_tk'            => 'PHAT',
                'ten_tk'           => 'TRẦN TẤN PHÁT',
                'loai'             => 'bank',
                'so_tai_khoan'     => '28101988888888', // bỏ khoảng trắng để dễ match
                'ngan_hang'        => 'TCB', // Techcombank
                'is_default_cash'  => false,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => 'Techcombank',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            // VÕ THỊ ÁNH TUYẾT – 0936692203 – MB
            [
                'ma_tk'            => 'ANH_TUYET',
                'ten_tk'           => 'VÕ THỊ ÁNH TUYẾT',
                'loai'             => 'bank',
                'so_tai_khoan'     => '0936692203',
                'ngan_hang'        => 'MB', // MBBank
                'is_default_cash'  => false,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => 'MBBank',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            // VÕ THỊ HỒNG TUYẾT – 1010161510008 – MB
            [
                'ma_tk'            => 'HONG_TUYET',
                'ten_tk'           => 'VÕ THỊ HỒNG TUYẾT',
                'loai'             => 'bank',
                'so_tai_khoan'     => '0935358761',
                'ngan_hang'        => 'TCB', // Techcombank
               
                'is_default_cash'  => false,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => 'Techcombank',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],

            // ⭐ Thêm mới: THẺ TÍN DỤNG - VÕ THỊ ÁNH TUYẾT (MB)
            [
                'ma_tk'            => 'ANH_TUYET_CC',                      // unique key
                'ten_tk'           => 'THẺ TÍN DỤNG - VÕ THỊ ÁNH TUYẾT',
                'loai'             => 'bank',                               // giữ 'bank' để lọt filter của API/UI hiện có
                'so_tai_khoan'     => 'CC-2203',                            // gợi ý: hiển thị 4 số cuối
                'ngan_hang'        => 'MB',
                'is_default_cash'  => false,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => 'Credit Card MBBank',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],

            // ⭐ CTY TNHH TIỆC CƯỚI PHÁT HOÀNG GIA – TECHCOMBANK 333444555666
[
    'ma_tk'            => 'PHG_WEDDING',                 // mã duy nhất
    'ten_tk'           => 'CTY TNHH TIỆC CƯỚI PHÁT HOÀNG GIA',
    'loai'             => 'bank',
    'so_tai_khoan'     => '333444555666',
    'ngan_hang'        => 'TCB',                         // Techcombank
    'is_default_cash'  => false,
    'is_active'        => true,
    'opening_balance'  => 0,
    'opening_date'     => null,
    'ghi_chu'          => 'Techcombank',
    'created_at'       => $now,
    'updated_at'       => $now,
],

        ];

        // ❗ Fix nhỏ: uniqueBy phải là ['ma_tk'] (dòng cũ có khoảng trắng và sai cú pháp)
        DB::table('tai_khoan_tiens')->upsert(
            $accounts,
         ['ma_tk'], // ✅ uniqueBy theo cột ma_tk
            [
                'ten_tk','loai','so_tai_khoan','ngan_hang','is_default_cash',
                'is_active','opening_balance','opening_date','ghi_chu','updated_at'
            ]
        );

        // Lấy map id theo ma_tk để tạo alias an toàn
        $map = DB::table('tai_khoan_tiens')
            ->whereIn('ma_tk', ['CASH','COMPANY','ZLP','PHAT','ANH_TUYET','HONG_TUYET','ANH_TUYET_CC',
        'PHG_WEDDING'])
            ->pluck('id', 'ma_tk');

        // -------------------------------
        // 2) Alias nhận diện (idempotent)
        //    Không dùng ký tự '|', vì phần so khớp đang dùng stripos().
        // -------------------------------
        $aliases = [];

        // COMPANY – Techcombank 87896789
        foreach (['TCB','Techcom','Techcombank'] as $bank) {
            $aliases[] = [
                'tai_khoan_id'    => $map['COMPANY'] ?? null,
                'pattern_bank'    => $bank,
                'pattern_account' => '87896789',
                'pattern_note'    => null,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        // PHAT – Techcombank 28101988888888
        foreach (['TCB','Techcom','Techcombank'] as $bank) {
            $aliases[] = [
                'tai_khoan_id'    => $map['PHAT'] ?? null,
                'pattern_bank'    => $bank,
                'pattern_account' => '28101988888888',
                'pattern_note'    => 'TRAN TAN PHAT',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        // ANH_TUYET – MBBank 0936692203
        foreach (['MB','MBBank'] as $bank) {
            $aliases[] = [
                'tai_khoan_id'    => $map['ANH_TUYET'] ?? null,
                'pattern_bank'    => $bank,
                'pattern_account' => '0936692203',
                'pattern_note'    => 'VO THI ANH TUYET',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        // HONG_TUYET – MBBank 1010161510008
        foreach (['MB','MBBank'] as $bank) {
            $aliases[] = [
                'tai_khoan_id'    => $map['HONG_TUYET'] ?? null,
                'pattern_bank'    => $bank,
                'pattern_account' => '0935358761',
                'pattern_note'    => 'VO THI HONG TUYET',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        // ZaloPay
        foreach (['ZaloPay','ZLP'] as $bank) {
            $aliases[] = [
                'tai_khoan_id'    => $map['ZLP'] ?? null,
                'pattern_bank'    => $bank,
                'pattern_account' => 'ZLP',
                'pattern_note'    => 'ZALOPAY',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        // ⭐ Thêm alias cho THẺ TÍN DỤNG - ÁNH TUYẾT (gợi ý match theo 4 số cuối)
        foreach (['MB','MBBank'] as $bank) {
            $aliases[] = [
                'tai_khoan_id'    => $map['ANH_TUYET_CC'] ?? null,
                'pattern_bank'    => $bank,
                'pattern_account' => '2203',                 // 4 số cuối in sao kê
                'pattern_note'    => 'VO THI ANH TUYET',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }


        // ⭐ Bổ sung alias dạng “CC-2203” (cover trường hợp phiếu lưu CC-2203)
foreach (['MB','MBBank'] as $bank) {
    $aliases[] = [
        'tai_khoan_id'    => $map['ANH_TUYET_CC'] ?? null,
        'pattern_bank'    => $bank,
        'pattern_account' => 'CC-2203',            // <— thêm alias exact này
        'pattern_note'    => 'VO THI ANH TUYET',
        'is_active'       => true,
        'created_at'      => $now,
        'updated_at'      => $now,
    ];
}

// PHG_WEDDING – Techcombank 333444555666
foreach (['TCB','Techcom','Techcombank'] as $bank) {
    $aliases[] = [
        'tai_khoan_id'    => $map['PHG_WEDDING'] ?? null,
        'pattern_bank'    => $bank,
        'pattern_account' => '333444555666',
        'pattern_note'    => 'CTY TNHH TIEC CUOI PHAT HOANG GIA',
        'is_active'       => true,
        'created_at'      => $now,
        'updated_at'      => $now,
    ];
}

        // Bỏ alias không có id
        $aliases = array_values(array_filter($aliases, fn ($a) => !empty($a['tai_khoan_id'])));

        foreach ($aliases as $alias) {
            $exists = DB::table('tai_khoan_aliases')->where([
                'tai_khoan_id'    => $alias['tai_khoan_id'],
                'pattern_bank'    => $alias['pattern_bank'],
                'pattern_account' => $alias['pattern_account'],
                'pattern_note'    => $alias['pattern_note'],
            ])->exists();

            if (! $exists) {
                DB::table('tai_khoan_aliases')->insert($alias);
            }
        }
    }
}
