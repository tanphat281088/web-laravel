<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BangCongThang extends Model
{
    use HasFactory;

    protected $table = 'bang_cong_thangs';

    protected $fillable = [
        'user_id',
        'thang', // 'YYYY-MM'

        'so_ngay_cong',
        'so_gio_cong',
        'di_tre_phut',
        've_som_phut',

        'nghi_phep_ngay',
        'nghi_phep_gio',
        'nghi_khong_luong_ngay',
        'nghi_khong_luong_gio',

        'lam_them_gio',
        'ghi_chu',

        'locked',
        'computed_at',
    ];

    protected $casts = [
        'so_ngay_cong' => 'decimal:2',
        'so_gio_cong'            => 'integer',
        'di_tre_phut'            => 'integer',
        've_som_phut'            => 'integer',
        'nghi_phep_ngay'         => 'integer',
        'nghi_phep_gio'          => 'integer',
        'nghi_khong_luong_ngay'  => 'integer',
        'nghi_khong_luong_gio'   => 'integer',
        'lam_them_gio'           => 'integer',
        'ghi_chu'                => 'array',
        'locked'                 => 'boolean',
        'computed_at'            => 'datetime',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    // ===== Quan hệ =====
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ===== Scopes =====
    public function scopeOfUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeMonth(Builder $q, string $thang): Builder
    {
        return $q->where('thang', $thang); // 'YYYY-MM'
    }

    public function scopeUnlocked(Builder $q): Builder
    {
        return $q->where('locked', false);
    }

    // ===== Helpers =====
    public function lock(): bool
    {
        return $this->update(['locked' => true]);
    }

    public function unlock(): bool
    {
        return $this->update(['locked' => false]);
    }

    public function isLocked(): bool
    {
        return (bool) $this->locked;
    }

    public function monthLabel(): string
    {
        return $this->thang; // có thể format human-readable nếu cần
    }
}
