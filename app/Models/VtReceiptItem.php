<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VtReceiptItem extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'vt_receipt_items';
    protected $guarded = [];
    protected $casts = [
        'don_gia'  => 'decimal:2',
        'so_luong' => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(VtReceipt::class, 'vt_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VtItem::class, 'vt_item_id');
    }
}
