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
                'so_tai_khoan'     => '1010161510008',
                'ngan_hang'        => 'MB', // MBBank
                'is_default_cash'  => false,
                'is_active'        => true,
                'opening_balance'  => 0,
                'opening_date'     => null,
                'ghi_chu'          => 'MBBank',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ];

        DB::table('tai_khoan_tiens')->upsert(
            $accounts,
            [' ma_tk' => 'ma_tk'], // dùng khóa duy nhất theo ma_tk (đảm bảo đúng key)
            [
                'ten_tk','loai','so_tai_khoan','ngan_hang','is_default_cash',
                'is_active','opening_balance','opening_date','ghi_chu','updated_at'
            ]
        );

        // Lấy map id theo ma_tk để tạo alias an toàn
        $map = DB::table('tai_khoan_tiens')
            ->whereIn('ma_tk', ['CASH','COMPANY','ZLP','PHAT','ANH_TUYET','HONG_TUYET'])
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
                'pattern_account' => '1010161510008',
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
