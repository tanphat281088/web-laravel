<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // View per-order: chỉ những đơn còn dư_nợ > 0, chưa tất toán, không tính đơn đã hủy
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW v_receivables_by_order AS
SELECT
    dh.id                                      AS don_hang_id,
    dh.ma_don_hang                             AS ma_don_hang,
    dh.khach_hang_id                           AS khach_hang_id,
    COALESCE(dh.ten_khach_hang, kh.ten_khach_hang) AS ten_khach_hang,
    COALESCE(dh.so_dien_thoai, kh.so_dien_thoai)   AS so_dien_thoai,

    -- Tổng phải thu/Đã thu/Dư nợ
    CAST(dh.tong_tien_can_thanh_toan AS SIGNED)                    AS tong_phai_thu,
    CAST(dh.so_tien_da_thanh_toan    AS SIGNED)                    AS da_thu,
    GREATEST(CAST(dh.tong_tien_can_thanh_toan AS SIGNED)
           - CAST(dh.so_tien_da_thanh_toan    AS SIGNED), 0)       AS du_no,

    -- Trạng thái
    dh.trang_thai_thanh_toan,
    dh.trang_thai_don_hang,
    dh.ngay_tao_don_hang

FROM don_hangs dh
LEFT JOIN khach_hangs kh ON kh.id = dh.khach_hang_id

WHERE
    -- Chỉ lấy đơn còn nợ
    (CAST(dh.tong_tien_can_thanh_toan AS SIGNED) - CAST(dh.so_tien_da_thanh_toan AS SIGNED)) > 0
    -- Chỉ lấy đơn chưa tất toán (0: chưa thanh toán, 1: thanh toán một phần)
    AND dh.trang_thai_thanh_toan IN (0, 1)
    -- Loại trừ đơn đã hủy (3)
    AND (dh.trang_thai_don_hang IS NULL OR dh.trang_thai_don_hang <> 3);
SQL);

        // View per-customer: tổng hợp theo khách + aging bucket
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW v_receivables_by_customer AS
SELECT
    o.khach_hang_id,
    MAX(o.ten_khach_hang)      AS ten_khach_hang,
    MAX(o.so_dien_thoai)       AS so_dien_thoai,

    -- Tổng hợp
    SUM(o.tong_phai_thu)       AS tong_phai_thu,
    SUM(o.da_thu)              AS da_thu,
    SUM(o.du_no)               AS con_lai,
    COUNT(*)                   AS so_don_con_no,

    -- Aging buckets theo ngày phát sinh (ngay_tao_don_hang)
    SUM(CASE WHEN DATEDIFF(CURDATE(), o.ngay_tao_don_hang) BETWEEN 0  AND 30  THEN o.du_no ELSE 0 END) AS age_0_30,
    SUM(CASE WHEN DATEDIFF(CURDATE(), o.ngay_tao_don_hang) BETWEEN 31 AND 60  THEN o.du_no ELSE 0 END) AS age_31_60,
    SUM(CASE WHEN DATEDIFF(CURDATE(), o.ngay_tao_don_hang) BETWEEN 61 AND 90  THEN o.du_no ELSE 0 END) AS age_61_90,
    SUM(CASE WHEN DATEDIFF(CURDATE(), o.ngay_tao_don_hang) > 90                          THEN o.du_no ELSE 0 END) AS age_91_plus

FROM v_receivables_by_order o
GROUP BY o.khach_hang_id;
SQL);
    }

    public function down(): void
    {
        // Xóa view (idempotent)
        DB::unprepared('DROP VIEW IF EXISTS v_receivables_by_customer;');
        DB::unprepared('DROP VIEW IF EXISTS v_receivables_by_order;');
    }
};
