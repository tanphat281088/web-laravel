<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\LuongThang
 *
 * - Snapshot lương theo tháng của từng user.
 * - Unique: (user_id, thang).
 * - Tôn trọng locked=true khi cập nhật.
 */
class LuongThang extends Model
{
    use HasFactory;

    protected $table = 'luong_thangs';

    protected $fillable = [
        'user_id',
        'thang',

        // Snapshot cấu hình
        'luong_co_ban',
        'cong_chuan',
        'he_so',

        // Tổng hợp công
        'so_ngay_cong',
        'so_gio_cong',

        // Cộng/Trừ
        'phu_cap',
        'thuong',
        'phat',

        // Kết quả tính
        'luong_theo_cong',
        'bhxh',
        'bhyt',
        'bhtn',
        'khau_tru_khac',
        'tam_ung',
        'thuc_nhan',

        // Trạng thái
        'locked',
        'computed_at',
        'ghi_chu',
    ];

    protected $casts = [
        'luong_co_ban'    => 'integer',
        'cong_chuan'      => 'integer',
        'he_so'           => 'decimal:2',
        'so_ngay_cong'    => 'decimal:2',
        'so_gio_cong'     => 'integer',
        'phu_cap'         => 'integer',
        'thuong'          => 'integer',
        'phat'            => 'integer',
        'luong_theo_cong' => 'integer',
        'bhxh'            => 'integer',
        'bhyt'            => 'integer',
        'bhtn'            => 'integer',
        'khau_tru_khac'   => 'integer',
        'tam_ung'         => 'integer',
        'thuc_nhan'       => 'integer',
        'locked'          => 'boolean',
        'computed_at'     => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // ===== Quan hệ =====
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ===== Scopes tiện ích =====
    public function scopeOfUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    /** thang = 'YYYY-MM' */
    public function scopeMonth($q, string $ym)
    {
        return $q->where('thang', $ym);
    }

    public function scopeLocked($q)
    {
        return $q->where('locked', true);
    }

    public function scopeUnlocked($q)
    {
        return $q->where('locked', false);
    }
}
