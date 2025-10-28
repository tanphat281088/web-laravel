<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class PhieuChi extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    /**
     * Giữ nguyên: cho phép mass-assign toàn bộ (đang dùng ở Service::create)
     */
    protected $guarded = [];

    /**
     * (Tuỳ chọn) ép kiểu một số cột thường dùng cho báo cáo/so sánh.
     * Không bắt buộc, nhưng an toàn nếu bạn đã dùng casts ở nơi khác.
     */
    protected $casts = [
        'ngay_chi'            => 'date',
        'loai_phieu_chi'      => 'integer',
        'nha_cung_cap_id'     => 'integer',
        'phieu_nhap_kho_id'   => 'integer',
        'so_tien'             => 'integer',
        'phuong_thuc_thanh_toan' => 'integer',
        'category_id'         => 'integer',
    ];

    /**
     * Hook hiện có: xoá field 'image' nếu lỡ được set từ FE
     * (giữ nguyên, không thay đổi để an toàn với luồng upload ảnh).
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            unset($model->attributes['image']);
        });
    }

    /* ===================== Quan hệ ===================== */

    /**
     * Ảnh đính kèm phiếu chi (đã có sẵn).
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Danh mục chi (cha→con) gắn với phiếu chi — phục vụ KQKD Mức A.
     * - NULL nếu phiếu chi cũ chưa phân loại hoặc loại nghiệp vụ không bắt buộc chọn danh mục.
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }
}
