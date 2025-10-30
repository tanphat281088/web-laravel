<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VtReceipt extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'vt_receipts';
    protected $guarded = [];
    protected $casts = [
        'ngay_ct'       => 'date',
        'tong_gia_tri'  => 'decimal:2',
        'tong_so_luong' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(VtReceiptItem::class, 'vt_receipt_id');
    }

    public function nhaCungCap(): BelongsTo
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_id');
    }
}
