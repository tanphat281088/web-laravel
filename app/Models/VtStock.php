<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VtStock extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'vt_stocks';
    protected $guarded = [];

    protected $casts = [
        'so_luong_ton' => 'integer',
        'gia_tri_ton'  => 'decimal:2',
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
    public function scopePositive($q)
    {
        return $q->where('so_luong_ton', '>', 0);
    }
}
