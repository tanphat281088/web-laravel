<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaiKhoanAlias extends Model
{
    // An toàn: cho phép mass assign (đã kiểm soát ở layer validate/service)
    protected $guarded = [];

    protected $casts = [
        'is_active'   => 'boolean',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /** ─────────────── Relationships ─────────────── */

    public function taiKhoan()
    {
        return $this->belongsTo(TaiKhoanTien::class, 'tai_khoan_id');
    }

    /** ─────────────── Scopes tiện dụng ─────────────── */

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Scope tìm alias khớp “thoáng” theo bank/account/note
     * - Không áp regex ở đây để giữ performance Eloquent;
     *   xử lý regex/khớp nâng cao sẽ làm ở service.
     */
    public function scopeLooseMatch($q, ?string $bank, ?string $account, ?string $note)
    {
        return $q->when($bank, fn($qq)   => $qq->where('pattern_bank', 'LIKE', '%' . $bank . '%'))
                 ->when($account, fn($qq)=> $qq->orWhere('pattern_account', 'LIKE', '%' . $account . '%'))
                 ->when($note, fn($qq)   => $qq->orWhere('pattern_note', 'LIKE', '%' . $note . '%'));
    }
}
