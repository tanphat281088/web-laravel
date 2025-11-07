<?php

namespace App\Services\SignMaker;

use Illuminate\Support\Str;

class SignRenderService
{
    /**
     * Render nội dung SVG/HTML từ template + dữ liệu (ví dụ cơ bản).
     * Triển khai thật thì build theo nhu cầu của anh.
     */
    public function render(array $payload): array
    {
        // ví dụ payload: ['template'=>'oval', 'text'=>'Happy Wedding', 'font'=>'SVN-Poppins', ...]
        $tpl  = (string)($payload['template'] ?? 'default');
        $text = (string)($payload['text'] ?? '');
        $font = (string)($payload['font'] ?? 'inherit');

        // Demo: trả về SVG đơn giản (anh thay bằng engine thật của anh)
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="300">
  <rect x="2" y="2" width="596" height="296" fill="#fff" stroke="#222" rx="24"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        style="font-family: {$font}; font-size: 32px; fill: #222;">
    {$this->escape($text)}
  </text>
</svg>
SVG;

        return [
            'svg' => $svg,
            'meta' => [
                'template' => $tpl,
                'font'     => $font,
                'len'      => Str::length($text),
            ],
        ];
    }

    protected function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
