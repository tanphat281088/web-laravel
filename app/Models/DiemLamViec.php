<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\DiemLamViec
 *
 * - Lưu các điểm làm việc (tâm geofence).
 * - Hỗ trợ tính khoảng cách (Haversine) & kiểm tra within_geofence.
 */
class DiemLamViec extends Model
{
    use HasFactory;

    protected $table = 'diem_lam_viecs';

    protected $fillable = [
        'ten',
        'dia_chi',
        'lat',
        'lng',
        'ban_kinh_m',
        'trang_thai',
    ];

    protected $casts = [
        'lat'         => 'float',
        'lng'         => 'float',
        'ban_kinh_m'  => 'integer',
        'trang_thai'  => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /** Mặc định: đơn vị bán kính địa cầu (m) cho Haversine */
    public const EARTH_RADIUS_M = 6371000;

    /** Scope: chỉ điểm đang hoạt động */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('trang_thai', 1);
    }

    /**
     * Tính khoảng cách Haversine (m) từ (lat,lng) bất kỳ đến tâm geofence của điểm làm việc này.
     */
    public function distanceTo(float $lat, float $lng): int
    {
        $lat1 = deg2rad($this->lat);
        $lng1 = deg2rad($this->lng);
        $lat2 = deg2rad($lat);
        $lng2 = deg2rad($lng);

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) ** 2
           + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;

        $c = 2 * asin(min(1, sqrt($a)));

        return (int) round(self::EARTH_RADIUS_M * $c);
    }

    /**
     * Kiểm tra toạ độ (lat,lng) có nằm trong geofence hay không.
     * Trả về: [bool $within, int $distanceM]
     */
    public function withinGeofence(float $lat, float $lng): array
    {
        $distance = $this->distanceTo($lat, $lng);
        return [$distance <= (int) $this->ban_kinh_m, $distance];
    }

    /**
     * Lấy điểm làm việc gần nhất (hoặc null nếu không có).
     */
    public static function nearest(float $lat, float $lng): ?self
    {
        // Tối giản: duyệt trong PHP (vì số điểm ít). Nếu nhiều điểm, có thể dùng SQL Haversine để sort.
        return self::query()->active()->get()
            ->sortBy(fn (self $d) => $d->distanceTo($lat, $lng))
            ->first();
    }
}
