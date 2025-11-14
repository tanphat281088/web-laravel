<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DonHang extends Model
{
    use DateTimeFormatter, UserNameResolver, UserTrackable;

    /**
     * ====== Trạng thái đơn hàng (NEW MAPPING) ======
     * 0 = Chưa giao, 1 = Đang giao, 2 = Đã giao, 3 = Đã hủy
     */
    public const TRANG_THAI_CHUA_GIAO = 0;
    public const TRANG_THAI_DANG_GIAO = 1;
    public const TRANG_THAI_DA_GIAO   = 2;
    public const TRANG_THAI_DA_HUY    = 3;

    /** Map nhãn trạng thái dùng chung trong BE */
    public const STATUS_LABELS = [
        self::TRANG_THAI_CHUA_GIAO => 'Chưa giao',
        self::TRANG_THAI_DANG_GIAO => 'Đang giao',
        self::TRANG_THAI_DA_GIAO   => 'Đã giao',
        self::TRANG_THAI_DA_HUY    => 'Đã hủy',
    ];

    protected $guarded = [];

    /**
     * Append thuộc tính dẫn xuất vào JSON/API.
     * Giữ nguyên để không phá vỡ FE hiện tại.
     * Muốn trả thêm text trạng thái thì có thể add: 'trang_thai_text'
     */
    protected $appends = ['so_tien_con_lai'];

    /**
     * Casts: giữ nguyên phần giờ của lịch giao; trạng thái là int.
     */
    protected $casts = [
        'nguoi_nhan_thoi_gian' => 'datetime',
        'trang_thai_don_hang'  => 'integer',
                'member_discount_percent' => 'integer',
        'member_discount_amount'  => 'integer',

    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            unset($model->attributes['image']);
        });
    }

    // ========== Quan hệ ==========
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class);
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class);
    }

    public function nguoiTao()
    {
        return $this->belongsTo(User::class, 'nguoi_tao');
    }

    public function phieuThu()
    {
        return $this->hasMany(PhieuThu::class);
    }

    public function chiTietPhieuThu()
    {
        return $this->hasMany(ChiTietPhieuThu::class);
    }

    // ========== Accessors ==========
    /**
     * Số tiền còn lại = max(0, cần thanh toán - đã thanh toán)
     */
    public function getSoTienConLaiAttribute(): int
    {
        $tong = (int) ($this->tong_tien_can_thanh_toan ?? 0);
        $daTT = (int) ($this->so_tien_da_thanh_toan ?? 0);
        $remain = $tong - $daTT;
        return $remain > 0 ? $remain : 0;
    }

    /**
     * Helper: trả về nhãn trạng thái theo value.
     * Dùng trong Resource/Transformer hoặc nơi xuất Excel/PDF.
     */
    public static function labelTrangThai(int $value): string
    {
        return self::STATUS_LABELS[$value] ?? 'Không rõ';
    }

    /**
     * Accessor (tùy chọn): nếu muốn FE nhận thêm text, có thể
     * bật trả ra bằng cách thêm 'trang_thai_text' vào $appends.
     */
    public function getTrangThaiTextAttribute(): string
    {
        return self::labelTrangThai((int) $this->trang_thai_don_hang);
    }

    // ========== Scopes hỗ trợ Giao hàng ==========
    /**
     * Lọc theo trạng thái (0/1/2/3). Bỏ qua nếu null.
     */
    public function scopeTrangThai($query, ?int $status)
    {
        if ($status === null) return $query;
        return $query->where('trang_thai_don_hang', $status);
    }

    /**
     * Lọc đơn giao TRONG NGÀY (theo ngày hệ thống).
     */
    public function scopeGiaoTrongNgay($query, ?Carbon $day = null)
    {
        $day = ($day ?? Carbon::today());
        return $query->whereDate('nguoi_nhan_thoi_gian', $day->toDateString());
    }

    /**
     * Lọc theo khoảng thời gian giao (datetime).
     */
    public function scopeGiaoTuDen($query, ?Carbon $from = null, ?Carbon $to = null)
    {
        if ($from) $query->where('nguoi_nhan_thoi_gian', '>=', $from);
        if ($to)   $query->where('nguoi_nhan_thoi_gian', '<=', $to);
        return $query;
    }
}
