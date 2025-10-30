<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VtLedger extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'vt_ledger';
    protected $guarded = [];

    // Gợi ý hằng số loại chứng từ
    public const CT_OPENING = 'OPENING';
    public const CT_RECEIPT = 'RECEIPT';
    public const CT_ISSUE   = 'ISSUE';
    public const CT_ADJUST  = 'ADJUST';

    protected $casts = [
        'ngay_ct'   => 'date',
        'don_gia'   => 'decimal:2',
        'so_luong_in'  => 'integer',
        'so_luong_out' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            unset($model->attributes['image']);
        });
    }

    // ---------------------------
    // Relationships
    // ---------------------------
    public function item(): BelongsTo
    {
        return $this->belongsTo(VtItem::class, 'vt_item_id');
    }

    // ---------------------------
    // Scopes
    // ---------------------------
    public function scopeByItem($q, $vtItemId)
    {
        if ($vtItemId) $q->where('vt_item_id', $vtItemId);
        return $q;
    }

    public function scopeByDateRange($q, ?string $from, ?string $to)
    {
        if ($from) $q->whereDate('ngay_ct', '>=', $from);
        if ($to)   $q->whereDate('ngay_ct', '<=', $to);
        return $q;
    }

    public function scopeByType($q, ?string $type)
    {
        if ($type) $q->where('loai_ct', $type);
        return $q;
    }
}
