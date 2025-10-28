<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\DonTuNghiPhep
 *
 * Lưu đơn từ (xin nghỉ phép/đi trễ/về sớm/khác).
 * Phối hợp với bảng 'don_tu_nghi_pheps' (đã tạo ở migration).
 */
class DonTuNghiPhep extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'don_tu_nghi_pheps';

    // ===== Constants =====
    // Trạng thái duyệt
    public const TRANG_THAI_PENDING  = 0;
    public const TRANG_THAI_APPROVED = 1;
    public const TRANG_THAI_REJECTED = 2;
    public const TRANG_THAI_CANCELED = 3;

    // Loại đơn (gợi ý)
    public const LOAI_NGHI_PHEP      = 'nghi_phep';
    public const LOAI_KHONG_LUONG    = 'khong_luong';
    public const LOAI_DI_TRE         = 'di_tre';
    public const LOAI_VE_SOM         = 've_som';
    public const LOAI_LAM_VIEC_TU_XA = 'lam_viec_tu_xa';
    public const LOAI_KHAC           = 'khac';

    protected $fillable = [
        'user_id',
        'tu_ngay',
        'den_ngay',
        'so_gio',
        'loai',
        'ly_do',            // lý do NV nhập khi tạo đơn
        'ly_do_tu_choi',    // <<< NEW: lý do QL nhập khi từ chối
        'trang_thai',
        'approver_id',
        'approved_at',
        'attachments',
    ];

    protected $casts = [
        'tu_ngay'       => 'date',
        'den_ngay'      => 'date',
        'so_gio'        => 'integer',
        'trang_thai'    => 'integer',
        'approved_at'   => 'datetime',
        'attachments'   => 'array',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
        'ly_do_tu_choi' => 'string',   // <<< NEW: cast rõ ràng cho FE nhận đúng kiểu
    ];

    // ===== Quan hệ =====
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // ===== Scopes tiện ích =====

    /** Lọc theo user tạo đơn */
    public function scopeOfUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /** Lọc theo khoảng ngày (bao trùm), so sánh theo tu_ngay/den_ngay */
    public function scopeBetweenDates(Builder $q, ?string $from, ?string $to): Builder
    {
        // Bao phủ mọi trường hợp: (tu_ngay..den_ngay) giao với (from..to)
        if ($from) {
            $q->where(function (Builder $sub) use ($from) {
                $sub->whereNull('den_ngay')->whereDate('tu_ngay', '>=', $from)
                    ->orWhere(function (Builder $sub2) use ($from) {
                        $sub2->whereNotNull('den_ngay')->whereDate('den_ngay', '>=', $from);
                    });
            });
        }
        if ($to) {
            $q->where(function (Builder $sub) use ($to) {
                $sub->whereNull('tu_ngay')->whereDate('den_ngay', '<=', $to)
                    ->orWhere(function (Builder $sub2) use ($to) {
                        $sub2->whereNotNull('tu_ngay')->whereDate('tu_ngay', '<=', $to);
                    });
            });
        }
        return $q;
    }

    /** Lọc theo trạng thái */
    public function scopeStatus(Builder $q, int $status): Builder
    {
        return $q->where('trang_thai', $status);
    }

    /** Lọc theo loại đơn */
    public function scopeType(Builder $q, string $loai): Builder
    {
        return $q->where('loai', $loai);
    }

    // ===== Helpers trạng thái/nhãn =====

    public function isPending(): bool  { return $this->trang_thai === self::TRANG_THAI_PENDING; }
    public function isApproved(): bool { return $this->trang_thai === self::TRANG_THAI_APPROVED; }
    public function isRejected(): bool { return $this->trang_thai === self::TRANG_THAI_REJECTED; }
    public function isCanceled(): bool { return $this->trang_thai === self::TRANG_THAI_CANCELED; }

    /** Nhãn tiếng Việt theo trạng thái */
    public function statusLabel(): string
    {
        return match ($this->trang_thai) {
            self::TRANG_THAI_APPROVED => 'Đã duyệt',
            self::TRANG_THAI_REJECTED => 'Từ chối',
            self::TRANG_THAI_CANCELED => 'Đã hủy',
            default                   => 'Chờ duyệt',
        };
    }

    /** Nhãn tiếng Việt theo loại */
    public function typeLabel(): string
    {
        return match ($this->loai) {
            self::LOAI_NGHI_PHEP      => 'Nghỉ phép',
            self::LOAI_KHONG_LUONG    => 'Nghỉ không lương',
            self::LOAI_DI_TRE         => 'Đi trễ',
            self::LOAI_VE_SOM         => 'Về sớm',
            self::LOAI_LAM_VIEC_TU_XA => 'Làm việc từ xa',
            default                   => 'Khác',
        };
    }

    /** Mô tả ngắn gọn (phục vụ log/audit) */
    public function shortDesc(): string
    {
        $from = $this->tu_ngay?->format('Y-m-d');
        $to   = $this->den_ngay?->format('Y-m-d');

        $when = $from && $to
            ? "{$from} → {$to}"
            : ($from ?? $to ?? '');

        $hours  = $this->so_gio ? " — {$this->so_gio} giờ" : '';
        $status = $this->statusLabel();

        return sprintf('%s (%s)%s — %s', $this->typeLabel(), $when, $hours, $status);
    }
}
