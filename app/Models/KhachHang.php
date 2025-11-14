<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class KhachHang extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    /**
     * Mở mass-assign cho tất cả cột (giữ nguyên như code gốc).
     * Field mới `kenh_lien_he` sẽ được nhận qua Request.
     */
    protected $guarded = [];

    /**
     * (Tuỳ chọn) ép kiểu rõ ràng để đảm bảo luôn là string/null.
     */
    protected $casts = [
        'kenh_lien_he' => 'string',
        'customer_mode'  => 'integer',  // 0 = normal, 1 = pass/CTV
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Không lưu trường image “ảo” nếu được gửi kèm từ FE
            unset($model->attributes['image']);
        });
    }

    public function loaiKhachHang()
    {
        return $this->belongsTo(LoaiKhachHang::class, 'loai_khach_hang_id');
    }

    // Kết nối sẵn với bảng images để lưu ảnh
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
