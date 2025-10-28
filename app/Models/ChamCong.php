<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ChamCong
 *
 * - Lưu checkin/checkout theo GPS.
 * - Ràng buộc duy nhất theo (user_id, type, ngay) đã đặt ở migration.
 */
class ChamCong extends Model
{
    use HasFactory;

    protected $table = 'cham_congs';

    protected $fillable = [
        'user_id',
        'type',              // 'checkin' | 'checkout'
        'lat',
        'lng',
        'accuracy_m',
        'distance_m',
        'within_geofence',   // 1|0
        'device_id',
        'ip',
        'checked_at',
        'ghi_chu',
    ];

    protected $casts = [
        'lat'              => 'float',
        'lng'              => 'float',
        'accuracy_m'       => 'integer',
        'distance_m'       => 'integer',
        'within_geofence'  => 'boolean',
        'checked_at'       => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // ===== Quan hệ =====
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ===== Scopes tiện ích =====

    /**
     * Lọc theo user.
     */
    public function scopeOfUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /**
     * Lọc theo khoảng thời gian checked_at (bao trùm).
     */
    public function scopeBetween(Builder $q, $from = null, $to = null): Builder
    {
        if ($from) {
            $q->where('checked_at', '>=', $from);
        }
        if ($to) {
            $q->where('checked_at', '<=', $to);
        }
        return $q;
    }

    /**
     * Lọc theo ngày (YYYY-MM-DD).
     */
    public function scopeOnDate(Builder $q, string $date): Builder
    {
        return $q->whereRaw('DATE(checked_at) = ?', [$date]);
    }

    /**
     * Chỉ check-in.
     */
    public function scopeCheckin(Builder $q): Builder
    {
        return $q->where('type', 'checkin');
    }

    /**
     * Chỉ check-out.
     */
    public function scopeCheckout(Builder $q): Builder
    {
        return $q->where('type', 'checkout');
    }

    // ===== Helpers =====

    public function isCheckin(): bool
    {
        return $this->type === 'checkin';
    }

    public function isCheckout(): bool
    {
        return $this->type === 'checkout';
    }

    /**
     * Nhãn tiếng Việt cho type.
     */
    public function typeLabel(): string
    {
        return $this->isCheckin() ? 'Chấm công vào' : 'Chấm công ra';
    }

    /**
     * Nhãn tiếng Việt cho within_geofence.
     */
    public function withinLabel(): string
    {
        return $this->within_geofence ? 'trong khu vực' : 'ngoài khu vực';
    }

    /**
     * Trả về chuỗi mô tả ngắn, phục vụ log/audit (tiếng Việt).
     */
    public function shortDesc(): string
    {
        $when = $this->checked_at instanceof CarbonInterface
            ? $this->checked_at->format('Y-m-d H:i')
            : (string) $this->checked_at;

        // Giữ distance theo mét như cũ (d=7m)
        $distance = is_null($this->distance_m) ? '' : ('d=' . (string) $this->distance_m . 'm');

        // Ví dụ: "Chấm công vào lúc 2025-10-23 09:30 — d=7m — trong khu vực"
        return sprintf(
            '%s lúc %s — %s — %s',
            $this->typeLabel(),
            $when,
            $distance,
            $this->withinLabel()
        );
    }
}
