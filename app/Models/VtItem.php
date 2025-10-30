<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VtItem extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'vt_items';
    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Giữ consistent với các model hiện có
            unset($model->attributes['image']);
        });
    }

    // ---------------------------
    // Relationships
    // ---------------------------
    public function ledgers(): HasMany
    {
        return $this->hasMany(VtLedger::class, 'vt_item_id');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(VtStock::class, 'vt_item_id');
    }

    // (Tuỳ chọn) gắn ảnh nếu bạn muốn lưu ảnh minh hoạ VT
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // ---------------------------
    // Scopes tiện lợi
    // ---------------------------
    public function scopeAsset($q)
    {
        return $q->where('loai', 'ASSET');
    }

    public function scopeConsumable($q)
    {
        return $q->where('loai', 'CONSUMABLE');
    }

    public function scopeActive($q)
    {
        return $q->where('trang_thai', 1);
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $like = '%'.trim($term).'%';
        return $q->where(function ($qq) use ($like) {
            $qq->where('ma_vt', 'like', $like)
               ->orWhere('ten_vt', 'like', $like)
               ->orWhere('danh_muc_vt', 'like', $like)
               ->orWhere('nhom_vt', 'like', $like);
        });
    }
}
