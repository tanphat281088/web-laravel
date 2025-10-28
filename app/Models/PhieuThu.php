<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PhieuThu extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    // === Loại phiếu thu (bổ sung TÀI CHÍNH để hạch toán vào 04) ===
    public const TYPE_TAI_CHINH = 'TAI_CHINH'; // Thu hoạt động tài chính

    protected $guarded = [];

    // Cast ngày giờ cho thuận tiện
    protected $casts = [
        'ngay_thu'   => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Giữ logic cũ của bạn
            unset($model->attributes['image']);

            // Chuẩn hoá cột "loại" (nếu có) về UPPERCASE để filter nhất quán
            $typeCol = static::typeColumn();
            if ($typeCol && isset($model->{$typeCol}) && is_string($model->{$typeCol})) {
                $model->{$typeCol} = strtoupper(trim($model->{$typeCol}));
            }
        });
    }

    /**
     * Xác định tên cột "loại" của phiếu thu (tương thích DB hiện tại).
     * Ưu tiên: 'loai' → 'loai_phieu_thu' → null nếu không tồn tại.
     */
    public static function typeColumn(): ?string
    {
        try {
            if (Schema::hasColumn((new static)->getTable(), 'loai')) {
                return 'loai';
            }
            if (Schema::hasColumn((new static)->getTable(), 'loai_phieu_thu')) {
                return 'loai_phieu_thu';
            }
        } catch (\Throwable $e) {
            // ignore schema check errors
        }
        return null;
    }

    /**
     * Scope: chỉ lấy phiếu thu HOẠT ĐỘNG TÀI CHÍNH (hạch toán vào 04)
     */
    public function scopeOnlyFinancial($query)
    {
        $col = static::typeColumn();
        if ($col) {
            return $query->where($col, self::TYPE_TAI_CHINH);
        }
        // Nếu không có cột loại → không lọc được
        return $query->whereRaw('1=0');
    }

    /**
     * Scope: loại trừ phiếu thu HOẠT ĐỘNG TÀI CHÍNH (phần còn lại vào 01)
     */
    public function scopeExcludeFinancial($query)
    {
        $col = static::typeColumn();
        if ($col) {
            return $query->where($col, '!=', self::TYPE_TAI_CHINH);
        }
        // Nếu không có cột loại → giữ nguyên (coi như tất cả là doanh thu bán hàng)
        return $query;
    }

    /**
     * Kiểm tra nhanh có phải phiếu thu tài chính
     */
    public function isFinancial(): bool
    {
        $col = static::typeColumn();
        return $col ? strtoupper((string)$this->{$col}) === self::TYPE_TAI_CHINH : false;
    }

    // Kết nối sẵn với bảng images để lưu ảnh (GIỮ NGUYÊN)
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
