<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoQuyEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'        => 'decimal:2', // dương = vào, âm = ra
        'ngay_ct'       => 'datetime',
        'reconciled_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /** ─────────────── Relationships ─────────────── */

    public function taiKhoan()
    {
        return $this->belongsTo(TaiKhoanTien::class, 'tai_khoan_id');
    }

    /** ─────────────── Scopes tiện dụng ─────────────── */

    public function scopeByAccount($q, $taiKhoanId = null)
    {
        return $q->when($taiKhoanId, fn($qq) => $qq->where('tai_khoan_id', $taiKhoanId));
    }

    public function scopeFromTo($q, $from = null, $to = null)
    {
        return $q
            ->when($from, fn($qq) => $qq->where('ngay_ct', '>=', $from))
            ->when($to,   fn($qq) => $qq->where('ngay_ct', '<=', $to));
    }

    public function scopeReconciled($q, ?bool $yes)
    {
        if ($yes === null) return $q;
        return $yes
            ? $q->whereNotNull('reconciled_at')
            : $q->whereNull('reconciled_at');
    }

    public function scopeRef($q, ?string $type = null, ?int $id = null)
    {
        return $q
            ->when($type, fn($qq) => $qq->where('ref_type', $type))
            ->when($id,   fn($qq) => $qq->where('ref_id', $id));
    }

    /** ─────────────── Helpers ─────────────── */

    public function getDirectionAttribute(): string
    {
        $a = (float) $this->amount;
        return $a >= 0 ? 'IN' : 'OUT';
    }
}
