<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZnsReviewInvite extends Model
{
    use SoftDeletes;

    protected $table = 'zns_review_invites';

    protected $fillable = [
        'khach_hang_id',
        'don_hang_id',
        'customer_code',
        'customer_name',
        'order_code',
        'order_date',
        'zns_status',
        'zns_sent_at',
        'zns_template_id',
        'zns_error_code',
        'zns_error_message',
        'nguoi_tao',
        'nguoi_cap_nhat',
    ];

    protected $casts = [
        'order_date'   => 'datetime',
        'zns_sent_at'  => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    // ========= Relationships (optional but useful) =========
    public function khachHang()
    {
        return $this->belongsTo(\App\Models\KhachHang::class, 'khach_hang_id');
    }

    public function donHang()
    {
        return $this->belongsTo(\App\Models\DonHang::class, 'don_hang_id');
    }

    // ========= Scopes tiện lọc =========
    public function scopeStatus($q, ?string $status)
    {
        return $status ? $q->where('zns_status', $status) : $q;
    }

    public function scopeBetweenOrderDate($q, ?string $from, ?string $to)
    {
        if ($from) $q->where('order_date', '>=', $from.' 00:00:00');
        if ($to)   $q->where('order_date', '<=', $to.' 23:59:59');
        return $q;
    }
}
