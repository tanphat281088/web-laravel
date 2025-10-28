<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignTemplateLibrarySeeder extends Seeder
{
    /**
     * Quy ước size:
     *  - L: 200×100mm
     *  - M: 160×80mm
     *  - S: 120×60mm
     */
    public function run(): void
    {
        $now = now();

        // Helper tạo style base theo size
        $sizeBase = function (string $size): array {
            return match ($size) {
                'L' => ['stroke_width_mm' => 0.8, 'padding_mm' => 10, 'safe_area_mm' => 5],
                'M' => ['stroke_width_mm' => 0.7, 'padding_mm' => 8,  'safe_area_mm' => 5],
                'S' => ['stroke_width_mm' => 0.6, 'padding_mm' => 6,  'safe_area_mm' => 5],
                default => ['stroke_width_mm' => 0.7, 'padding_mm' => 8, 'safe_area_mm' => 5],
            };
        };

        // Helper map size -> width/height
        $sizeDims = fn (string $size) => match ($size) {
            'L' => [200, 100],
            'M' => [160,  80],
            'S' => [120,  60],
            default => [160, 80],
        };

        // Tone mặc định PHG
        $brand = [
            'text_color'   => '#2E3A63',
            'stroke_color' => '#C83D5D',
            'bg_color'     => '#00000000', // trong suốt
            'variant'      => 'double',    // outline đôi
            'font_family'  => '"Be Vietnam Pro","DejaVu Sans",Montserrat,Arial,sans-serif',
            'corner_radius_mm' => 8,       // roundrect mặc định
        ];

        // 6 nhóm × 3 size
        $defs = [];

        foreach (['L','M','S'] as $sz) {
            [$w, $h] = $sizeDims($sz);

            // 1) RECT · Minimal (khung phẳng, viền đôi)
            $defs[] = [
                'code'       => "RECT_{$sz}_MINIMAL",
                'name'       => "Chữ nhật {$sz} · Minimal",
                'shape'      => 'rect',
                'width_mm'   => $w,
                'height_mm'  => $h,
                'bleed_mm'   => 3,
                'style'      => array_replace_recursive($brand, $sizeBase($sz), [
                    'variant'          => 'double',
                    'stroke_color'     => '#C83D5D',
                    'bg_color'         => '#FFFFFF',
                    'corner_radius_mm' => 0,
                ]),
                'export_prefs' => (object) [],
            ];

            // 2) RECT · Oriental v1 (hoa văn góc Á Đông)
            $orn = match ($sz) {
                'L' => ['orn_corner_mm' => 10, 'orn_gap_mm' => 1.6, 'orn_lines' => 3],
                'M' => ['orn_corner_mm' =>  8, 'orn_gap_mm' => 1.4, 'orn_lines' => 3],
                'S' => ['orn_corner_mm' =>  6, 'orn_gap_mm' => 1.2, 'orn_lines' => 3],
            };
            $defs[] = [
                'code'       => "RECT_{$sz}_ORN_V1",
                'name'       => "Chữ nhật {$sz} · Hoa văn góc",
                'shape'      => 'rect',
                'width_mm'   => $w,
                'height_mm'  => $h,
                'bleed_mm'   => 3,
                'style'      => array_replace_recursive($brand, $sizeBase($sz), [
                    'variant'      => 'outline',
                    'stroke_color' => '#6A0E0E', // burgundy
                    'bg_color'     => '#00000000',
                    'corner_radius_mm' => 0,
                    'ornament'     => 'oriental_v1',
                ], $orn),
                'export_prefs' => (object) [],
            ];

            // 3) ROUNDRECT · Minimal (bo góc sang, viền đôi trong 0.3mm)
            $defs[] = [
                'code'       => "ROUNDRECT_{$sz}_MINIMAL",
                'name'       => "Chữ nhật bo góc {$sz} · Minimal",
                'shape'      => 'roundrect',
                'width_mm'   => $w,
                'height_mm'  => $h,
                'bleed_mm'   => 3,
                'style'      => array_replace_recursive($brand, $sizeBase($sz), [
                    'variant'          => 'double',
                    'bg_color'         => '#FFFFFF',
                    'stroke_color'     => '#C83D5D',
                    'corner_radius_mm' => match ($sz) { 'L' => 14, 'M' => 12, 'S' => 10 },
                ]),
                'export_prefs' => (object) [],
            ];

            // 4) OVAL · Elegant
            $defs[] = [
                'code'       => "OVAL_{$sz}_ELEGANT",
                'name'       => "Oval {$sz} · Elegant",
                'shape'      => 'oval',
                'width_mm'   => $w,
                'height_mm'  => $h,
                'bleed_mm'   => 3,
                'style'      => array_replace_recursive($brand, $sizeBase($sz), [
                    'variant'      => 'double',
                    'bg_color'     => '#FFFFFF',
                    'stroke_color' => '#C83D5D',
                ]),
                'export_prefs' => (object) [],
            ];

            // 5) HEART · Minimal (giảm phình ~12%)
            $defs[] = [
                'code'       => "HEART_{$sz}_MINIMAL",
                'name'       => "Trái tim {$sz} · Minimal",
                'shape'      => 'heart',
                'width_mm'   => $w,
                'height_mm'  => $h,
                'bleed_mm'   => 3,
                'style'      => array_replace_recursive($brand, $sizeBase($sz), [
                    'variant'      => 'outline',
                    'bg_color'     => '#FFFFFF',
                    'stroke_color' => '#C83D5D',
                    // tim cần dư địa hơn
                    'padding_mm'   => match ($sz) { 'L' => 12, 'M' => 10, 'S' => 8 },
                ]),
                'export_prefs' => (object) [],
            ];

            // 6) CLOUD · Soft
            $defs[] = [
                'code'       => "CLOUD_{$sz}_SOFT",
                'name'       => "Mây {$sz} · Soft",
                'shape'      => 'cloud',
                'width_mm'   => $w,
                'height_mm'  => $h,
                'bleed_mm'   => 3,
                'style'      => array_replace_recursive($brand, $sizeBase($sz), [
                    'variant'      => 'outline',
                    'bg_color'     => '#FFFFFF',
                    'stroke_color' => '#C83D5D',
                    // mây cũng cần dư địa hơn
                    'padding_mm'   => match ($sz) { 'L' => 12, 'M' => 10, 'S' => 8 },
                ]),
                'export_prefs' => (object) [],
            ];

            // (Tuỳ chọn) 7) RIBBON · Classic
            $defs[] = [
                'code'       => "RIBBON_{$sz}_CLASSIC",
                'name'       => "Ruy băng {$sz} · Classic",
                'shape'      => 'ribbon',
                'width_mm'   => $w,
                'height_mm'  => $h,
                'bleed_mm'   => 3,
                'style'      => array_replace_recursive($brand, $sizeBase($sz), [
                    'variant'      => 'double',
                    'bg_color'     => '#FFFFFF',
                    'stroke_color' => '#C83D5D',
                ]),
                'export_prefs' => (object) [],
            ];
        }

        // Upsert theo code để tránh trùng lặp khi chạy nhiều lần
        $rows = [];
        foreach ($defs as $d) {
            $rows[] = [
                'code'         => $d['code'],
                'name'         => $d['name'],
                'shape'        => $d['shape'],
                'width_mm'     => $d['width_mm'],
                'height_mm'    => $d['height_mm'],
                'bleed_mm'     => $d['bleed_mm'],
                'style'        => json_encode($d['style'], JSON_UNESCAPED_UNICODE),
                'export_prefs' => json_encode($d['export_prefs']),
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        DB::table('sign_templates')->upsert($rows, ['code'], [
            'name','shape','width_mm','height_mm','bleed_mm','style','export_prefs','updated_at'
        ]);
    }
}

// === Elegant Gold (rect, double line, gap + floral corner) ===
$addElegant = function(string $sz) use ($brand, $sizeBase, $sizeDims) {
    [$w, $h] = $sizeDims($sz);
    $conf = match ($sz) {
        'L' => ['stroke'=>0.8,'pad'=>10,'gap'=>14,'off'=>2.0,'fl'=>18],
        'M' => ['stroke'=>0.7,'pad'=> 8,'gap'=>12,'off'=>1.8,'fl'=>16],
        'S' => ['stroke'=>0.6,'pad'=> 6,'gap'=>10,'off'=>1.6,'fl'=>14],
    };
    return [
        'code'       => "RECT_{$sz}_ELEGANT_GOLD",
        'name'       => "Chữ nhật {$sz} · Elegant Gold",
        'shape'      => 'rect',
        'width_mm'   => $w,
        'height_mm'  => $h,
        'bleed_mm'   => 3,
        'style'      => array_replace_recursive($brand, $sizeBase($sz), [
            'variant'          => 'double',
            'bg_color'         => '#00000000',
            'stroke_color'     => '#C99636',
            'text_color'       => '#2E3A63',
            'corner_radius_mm' => 0,
            'stroke_width_mm'  => $conf['stroke'],
            'padding_mm'       => $conf['pad'],
            'ornament'         => 'elegant_gold_v1',
            'cap_gap_mm'       => $conf['gap'],
            'line2_offset_mm'  => $conf['off'],
            'floral_len_mm'    => $conf['fl'],
        ]),
        'export_prefs' => (object) [],
    ];
};

$defs[] = $addElegant('L');
$defs[] = $addElegant('M');
$defs[] = $addElegant('S');
