<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignTemplate extends Model
{
    protected $table = 'sign_templates';

    protected $fillable = [
        'code', 'name', 'shape', 'width_mm', 'height_mm', 'bleed_mm',
        'style', 'export_prefs',
    ];

    protected $casts = [
        'style'        => 'array',
        'export_prefs' => 'array',
    ];

    // Hằng số shape cho code clear & tránh typo
    public const SHAPES = ['oval','rect','roundrect','cloud','heart','ribbon'];

    // Kích thước thật tính theo px ở 300dpi (tiện cho render PNG)
    public function widthPx(int $dpi = 300): int
    {
        // 1 inch = 25.4 mm
        return (int) round(($this->width_mm / 25.4) * $dpi);
    }

    public function heightPx(int $dpi = 300): int
    {
        return (int) round(($this->height_mm / 25.4) * $dpi);
    }
}
