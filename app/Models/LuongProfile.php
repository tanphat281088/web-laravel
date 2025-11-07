<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\LuongProfile
 *
 * - Hồ sơ lương hiện hành của từng user (cấu hình gốc).
 * - Mỗi user đúng 1 record (unique user_id).
 */
class LuongProfile extends Model
{
    use HasFactory;

    protected $table = 'luong_profiles';

    // Bảo vệ tối thiểu: cho phép fill an toàn các field đã định nghĩa
    protected $fillable = [
        'user_id',
        'muc_luong_co_ban',
        'cong_chuan',
        'he_so',
        'phu_cap_mac_dinh',
        'pt_bhxh',
        'pt_bhyt',
        'pt_bhtn',
        'hieu_luc_tu',
        'ghi_chu',
    ];

    protected $casts = [
        'muc_luong_co_ban' => 'integer',
        'cong_chuan'       => 'integer',
        'he_so'            => 'decimal:2',
        'phu_cap_mac_dinh' => 'integer',
        'pt_bhxh'          => 'decimal:2',
        'pt_bhyt'          => 'decimal:2',
        'pt_bhtn'          => 'decimal:2',
        'hieu_luc_tu'      => 'date',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
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
}
