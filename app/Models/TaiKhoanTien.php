<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaiKhoanTien extends Model
{
    // An toàn: cho phép mass-assign có kiểm soát
    protected $guarded = [];

    protected $casts = [
        'is_default_cash'  => 'boolean',
        'is_active'        => 'boolean',
        'opening_balance'  => 'decimal:2',
        'opening_date'     => 'date',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /** ───────────────── Relationships ───────────────── */

    // Alias nhận diện (giảm nhập liệu)
    public function aliases()
    {
        return $this->hasMany(TaiKhoanAlias::class, 'tai_khoan_id');
    }

    // (Sẽ dùng ở bước sau) Dòng sổ quỹ theo tài khoản
    public function soQuyEntries()
    {
        return $this->hasMany(SoQuyEntry::class, 'tai_khoan_id');
    }

    /** ───────────────── Scopes tiện dụng ───────────────── */

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeCash($q)
    {
        return $q->where('loai', 'cash');
    }

    public function scopeBank($q)
    {
        return $q->where('loai', 'bank');
    }

    public function scopeEwallet($q)
    {
        return $q->where('loai', 'ewallet');
    }
}
