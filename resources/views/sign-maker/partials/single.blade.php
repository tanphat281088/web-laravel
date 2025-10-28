@php
  // ====== Kích thước & style cơ bản ======
  /** @var \App\Models\SignTemplate $tpl */
  $W       = (float) ($tpl->width_mm  ?? 0);
  $H       = (float) ($tpl->height_mm ?? 0);
  $bleed   = (float) ($bleed_mm       ?? 3.0);
  $safe    = (float) ($safe_mm        ?? 5.0);

  $style   = $style ?? [];
  $variant = strtolower($style['variant'] ?? 'double'); // double|outline|solid

  $bg      = $style['bg_color']     ?? '#FFF8FA';
  $stroke  = $style['stroke_color'] ?? '#2E3A63';
  $text    = $style['text_color']   ?? '#2E3A63';
  $accent  = $style['accent_color'] ?? '#C83D5D';

  $strokeW = (float) ($style['stroke_width_mm']  ?? 0.8);
  $pad     = (float) ($style['padding_mm']       ?? 6.0);
  $radius  = (float) ($style['corner_radius_mm'] ?? 8.0);

  $shape   = strtolower($tpl->shape ?? 'rect');
  $ornType = strtolower($style['ornament'] ?? 'none');

  // Vùng vẽ (đã trừ bleed)
  $X0 = $bleed;           $Y0 = $bleed;
  $IW = max(1.0, $W - 2*$bleed);
  $IH = max(1.0, $H - 2*$bleed);

  // Vùng chữ (trừ safe + padding)
  $innerX = $X0 + $safe + $pad;
  $innerY = $Y0 + $safe + $pad;
  $innerW = max(1.0, $IW - 2*($safe + $pad));
  $innerH = max(1.0, $IH - 2*($safe + $pad));

  // Font
  $fontPx   = (float) ($font_size ?? 18);   // pt
  $lineH    = $fontPx * 1.28;

  // Tâm
  $cx = $X0 + $IW / 2.0;
  $cy = $Y0 + $IH / 2.0;

  // Helper: path cho các shape “vẽ tự do”
  $path = function(string $kind) use ($X0,$Y0,$IW,$IH,$radius) {
      switch ($kind) {
          case 'roundrect':
          case 'rect':
          case 'oval':
              return null; // dùng phần tử SVG sẵn
          case 'heart':
              $x = $X0; $y = $Y0; $w = $IW; $h = $IH;
              $cx = $x + $w/2; $cy = $y + $h/2;
              $top = $y + 0.28*$h; $bot = $y + 0.92*$h;
              $left = $x + 0.12*$w; $right = $x + 0.88*$w;
              return "M {$cx},{$bot}
                      C ".($cx+0.22*$w).",".($bot-0.18*$h)." {$right},".($cy+0.10*$h)." {$right},".($top+0.08*$h)."
                      C {$right},".($y-0.02*$h)." ".($cx+0.18*$w).",".($y+0.02*$h)." {$cx},".($top+0.10*$h)."
                      C ".($cx-0.18*$w).",".($y+0.02*$h)." {$left},".($y-0.02*$h)." {$left},".($top+0.08*$h)."
                      C {$left},".($cy+0.10*$h)." ".($cx-0.22*$w).",".($bot-0.18*$h)." {$cx},{$bot} Z";
          case 'cloud':
              $x = $X0; $y = $Y0; $w = $IW; $h = $IH;
              return "M ".($x+0.20*$w).",".($y+0.60*$h)."
                      C ".($x+0.10*$w).",".($y+0.40*$h)." ".($x+0.20*$w).",".($y+0.25*$h)." ".($x+0.35*$w).",".($y+0.28*$h)."
                      C ".($x+0.40*$w).",".($y+0.12*$h)." ".($x+0.60*$w).",".($y+0.12*$h)." ".($x+0.66*$w).",".($y+0.28*$h)."
                      C ".($x+0.80*$w).",".($y+0.25*$h)." ".($x+0.90*$w).",".($y+0.38*$h)." ".($x+0.84*$w).",".($y+0.55*$h)."
                      C ".($x+0.95*$w).",".($y+0.60*$h)." ".($x+0.95*$w).",".($y+0.75*$h)." ".($x+0.84*$w).",".($y+0.78*$h)."
                      C ".($x+0.80*$w).",".($y+0.90*$h)." ".($x+0.60*$w).",".($y+0.95*$h)." ".($x+0.52*$w).",".($y+0.84*$h)."
                      C ".($x+0.40*$w).",".($y+0.95*$h)." ".($x+0.20*$w).",".($y+0.90*$h)." ".($x+0.22*$w).",".($y+0.72*$h)."
                      C ".($x+0.10*$w).",".($y+0.70*$h)." ".($x+0.10*$w).",".($y+0.55*$h)." ".($x+0.20*$w).",".($y+0.60*$h)." Z";
          case 'ribbon':
          default:
              return null;
      }
  };

  // Vẽ double-stroke: lớp ngoài + lớp trong
  $drawDoubleStroke = function ($shapeTag, $attrs, $innerThin=true) use ($variant,$stroke,$strokeW) {
      $html = '';
      $stroke2 = $innerThin ? max(0.3, $strokeW*0.45) : $strokeW;
      if ($variant === 'double') {
          $html .= "<$shapeTag $attrs fill='none' stroke='{$stroke}' stroke-width='{$strokeW}mm' />";
          $html .= "<$shapeTag $attrs fill='none' stroke='{$stroke}' stroke-width='{$stroke2}mm' opacity='0.65' />";
      } elseif ($variant === 'solid') {
          $html .= "<$shapeTag $attrs fill='{$stroke}' stroke='{$stroke}' stroke-width='{$strokeW}mm' />";
      } else { // outline
          $html .= "<$shapeTag $attrs fill='none' stroke='{$stroke}' stroke-width='{$strokeW}mm' />";
      }
      return $html;
  };
@endphp

<svg xmlns="http://www.w3.org/2000/svg"
     width="{{ number_format($W,2,'.','') }}mm"
     height="{{ number_format($H,2,'.','') }}mm"
     viewBox="0 0 {{ number_format($W,2,'.','') }} {{ number_format($H,2,'.','') }}"
     preserveAspectRatio="xMidYMid meet">

  {{-- Nền trắng cắt theo bleed để Dompdf không render mép trong suốt --}}
  <rect x="0" y="0" width="{{ $W }}" height="{{ $H }}" fill="#FFFFFF"/>

  {{-- Vùng vẽ thực tế (đã trừ bleed) --}}
  <g>
    @switch($shape)
      @case('rect')
      @case('roundrect')
        <rect x="{{ $X0 }}" y="{{ $Y0 }}"
              width="{{ $IW }}" height="{{ $IH }}"
              rx="{{ $shape==='roundrect' ? $radius : 0 }}"
              ry="{{ $shape==='roundrect' ? $radius : 0 }}"
              fill="{{ $bg }}" />
        {!! $drawDoubleStroke('rect', "x='{$X0}' y='{$Y0}' width='{$IW}' height='{$IH}' rx='".($shape==='roundrect' ? $radius : 0)."' ry='".($shape==='roundrect' ? $radius : 0)."'" ) !!}

        {{-- Ornament: Oriental v1 (4 góc kiểu Á Đông) --}}
        @if($ornType === 'oriental_v1' && $shape === 'rect')
          @php
            $cLen = (float)($style['orn_corner_mm'] ?? 8);
            $gap  = (float)($style['orn_gap_mm']    ?? 1.4);
            $cnt  = (int)  ($style['orn_lines']     ?? 3);
            $sw   = (float)($style['stroke_width_mm'] ?? 0.6);
            $c    = $stroke;
            $drawL = function($x,$y,$dx,$dy,$len,$color,$sw){
              return "<path d='M {$x},{$y} l {$dx},0 0,{$dy}' fill=\"none\" stroke=\"{$color}\" stroke-width=\"{$sw}mm\"/>";
            };
            $html = '';
            for($i=0;$i<$cnt;$i++){
              $off = $i * $gap;
              $len = max(1.0, $cLen - $i*$gap);
              // TL
              $html .= $drawL($X0+$off,           $Y0+$off,            $len,  $len, $len, $c, $sw);
              // TR
              $html .= $drawL($X0+$IW-$off,       $Y0+$off,            -$len, $len, $len, $c, $sw);
              // BL
              $html .= $drawL($X0+$off,           $Y0+$IH-$off,        $len,  -$len,$len, $c, $sw);
              // BR
              $html .= $drawL($X0+$IW-$off,       $Y0+$IH-$off,        -$len, -$len,$len, $c, $sw);
            }
            echo $html;
          @endphp
        @endif

        {{-- Ornament: Elegant Gold v1 (double line + gap + flourish) --}}
        @if($ornType === 'elegant_gold_v1' && $shape === 'rect')
          @php
            $gap   = (float)($style['cap_gap_mm'] ?? 12);
            $off2  = (float)($style['line2_offset_mm'] ?? 1.8);
            $flLen = (float)($style['floral_len_mm'] ?? 16);
            $sw    = (float)($style['stroke_width_mm'] ?? 0.7);
            $c     = $stroke;

            $x1 = $X0; $x2 = $X0 + $IW; $yTop = $Y0; $yBot = $Y0 + $IH;
            $half = $X0 + $IW/2;

            // line trên/dưới có "gap" ở giữa
            echo "<line x1='{$x1}' y1='{$yTop}' x2='".($half-$gap/2)."' y2='{$yTop}' stroke='{$c}' stroke-width='{$sw}mm'/>";
            echo "<line x1='".($half+$gap/2)."' y1='{$yTop}' x2='{$x2}' y2='{$yTop}' stroke='{$c}' stroke-width='{$sw}mm'/>";
            echo "<line x1='{$x1}' y1='{$yBot}' x2='".($half-$gap/2)."' y2='{$yBot}' stroke='{$c}' stroke-width='{$sw}mm'/>";
            echo "<line x1='".($half+$gap/2)."' y1='{$yBot}' x2='{$x2}' y2='{$yBot}' stroke='{$c}' stroke-width='{$sw}mm'/>";

            // cạnh dọc ngoài
            echo "<line x1='{$X0}' y1='{$Y0}' x2='{$X0}' y2='".($Y0+$IH)."' stroke='{$c}' stroke-width='{$sw}mm'/>";
            echo "<line x1='".($X0+$IW)."' y1='{$Y0}' x2='".($X0+$IW)."' y2='".($Y0+$IH)."' stroke='{$c}' stroke-width='{$sw}mm'/>";

            // viền trong lùi vào off2
            $ix1=$X0+$off2; $iy1=$Y0+$off2; $iW=$IW-2*$off2; $iH=$IH-2*$off2; $sw2=max(0.3,$sw*0.5);
            echo "<line x1='{$ix1}' y1='{$iy1}' x2='".($ix1+$iW/2-$gap/2)."' y2='{$iy1}' stroke='{$c}' stroke-width='{$sw2}mm' opacity='0.85'/>";
            echo "<line x1='".($ix1+$iW/2+$gap/2)."' y1='{$iy1}' x2='".($ix1+$iW)."' y2='{$iy1}' stroke='{$c}' stroke-width='{$sw2}mm' opacity='0.85'/>";
            echo "<line x1='{$ix1}' y1='".($iy1+$iH)."' x2='".($ix1+$iW/2-$gap/2)."' y2='".($iy1+$iH)."' stroke='{$c}' stroke-width='{$sw2}mm' opacity='0.85'/>";
            echo "<line x1='".($ix1+$iW/2+$gap/2)."' y1='".($iy1+$iH)."' x2='".($ix1+$iW)."' y2='".($iy1+$iH)."' stroke='{$c}' stroke-width='{$sw2}mm' opacity='0.85'/>";
            echo "<line x1='{$ix1}' y1='{$iy1}' x2='{$ix1}' y2='".($iy1+$iH)."' stroke='{$c}' stroke-width='{$sw2}mm' opacity='0.85'/>";
            echo "<line x1='".($ix1+$iW)."' y1='{$iy1}' x2='".($ix1+$iW)."' y2='".($iy1+$iH)."' stroke='{$c}' stroke-width='{$sw2}mm' opacity='0.85'/>";

            // flourish góc (stroke-only)
            $fl = function($x,$y,$sx,$sy,$len,$c,$sw) {
              $p = "M $x,$y
                    c ".( 0.25*$sx*$len).",".(-0.15*$sy*$len)." ".(0.50*$sx*$len).",".(0.05*$sy*$len)." ".(0.70*$sx*$len).",".(0.00*$sy*$len)."
                    c ".( 0.15*$sx*$len).",".( 0.10*$sy*$len)." ".(0.10*$sx*$len).",".(0.25*$sy*$len)." ".(0.00*$sx*$len).",".(0.35*$sy*$len)."
                    m ".(-0.30*$sx*$len).",".(0.10*$sy*$len)."
                    c ".( 0.10*$sx*$len).",".(-0.20*$sy*$len)." ".(0.30*$sx*$len).",".(-0.10*$sy*$len)." ".(0.40*$sx*$len).",".(0.00*$sy*$len);
              return "<path d='{$p}' fill='none' stroke='{$c}' stroke-width='{$sw}mm' stroke-linecap='round' stroke-linejoin='round'/>";
            };
            echo $fl($X0+2,         $Y0+$IH-2,   +1, -1, $flLen, $c, $sw);
            echo $fl($X0+2,         $Y0+2,       +1, +1, $flLen, $c, $sw);
            echo $fl($X0+$IW-2,     $Y0+2,       -1, +1, $flLen, $c, $sw);
            echo $fl($X0+$IW-2,     $Y0+$IH-2,   -1, -1, $flLen, $c, $sw);
          @endphp
        @endif
        @break

      @case('oval')
        <ellipse cx="{{ $cx }}" cy="{{ $cy }}"
                 rx="{{ $IW/2 }}" ry="{{ $IH/2 }}"
                 fill="{{ $bg }}" />
        {!! $drawDoubleStroke('ellipse', "cx='{$cx}' cy='{$cy}' rx='".($IW/2)."' ry='".($IH/2)."'" ) !!}
        @break

      @case('heart')
        @php $p = $path('heart'); @endphp
        <path d="{{ $p }}" fill="{{ $bg }}"/>
        {!! $drawDoubleStroke('path', "d='{$p}'" ) !!}
        @break

      @case('cloud')
        @php $p = $path('cloud'); @endphp
        <path d="{{ $p }}" fill="{{ $bg }}"/>
        {!! $drawDoubleStroke('path', "d='{$p}'" ) !!}
        @break

      @case('ribbon')
        {{-- Banner trung tâm --}}
        <rect x="{{ $X0 + 0.10*$IW }}" y="{{ $Y0 + 0.32*$IH }}"
              width="{{ 0.80*$IW }}" height="{{ 0.36*$IH }}"
              rx="{{ min($radius, 0.18*$IH) }}" ry="{{ min($radius, 0.18*$IH) }}"
              fill="{{ $bg }}" />
        {!! $drawDoubleStroke('rect', "x='".($X0 + 0.10*$IW)."' y='".($Y0 + 0.32*$IH)."' width='".(0.80*$IW)."' height='".(0.36*$IH)."' rx='".min($radius,0.18*$IH)."' ry='".min($radius,0.18*$IH)."'" ) !!}

        {{-- Cánh trái --}}
        <path d="M {{ $X0+0.02*$IW }},{{ $Y0+0.42*$IH }}
                 L {{ $X0+0.10*$IW }},{{ $Y0+0.32*$IH }}
                 L {{ $X0+0.10*$IW }},{{ $Y0+0.68*$IH }}
                 Z" fill="{{ $bg }}" stroke="{{ $stroke }}" stroke-width="{{ $strokeW }}mm" />
        {{-- Cánh phải --}}
        <path d="M {{ $X0+0.98*$IW }},{{ $Y0+0.42*$IH }}
                 L {{ $X0+0.90*$IW }},{{ $Y0+0.32*$IH }}
                 L {{ $X0+0.90*$IW }},{{ $Y0+0.68*$IH }}
                 Z" fill="{{ $bg }}" stroke="{{ $stroke }}" stroke-width="{{ $strokeW }}mm" />
        @break

      @default
        {{-- fallback: rect --}}
        <rect x="{{ $X0 }}" y="{{ $Y0 }}" width="{{ $IW }}" height="{{ $IH }}" fill="{{ $bg }}" />
        {!! $drawDoubleStroke('rect', "x='{$X0}' y='{$Y0}' width='{$IW}' height='{$IH}'" ) !!}
    @endswitch
  </g>

  {{-- DEBUG safe-area --}}
  {{-- <rect x="{{ $innerX }}" y="{{ $innerY }}" width="{{ $innerW }}" height="{{ $innerH }}"
        fill="none" stroke="#00AAFF" stroke-width="0.2mm" opacity="0.25"/> --}}

  {{-- ================== TEXT (auto clamp vào hộp) ================== --}}
  @php
    // Hộp chữ sau bleed + safe + padding
    $innerX = $X0 + $safe + $pad;
    $innerY = $Y0 + $safe + $pad;
    $innerW = max(1.0, $IW - 2*($safe + $pad));
    $innerH = max(1.0, $IH - 2*($safe + $pad));

    // Thoáng: 86% ngang, 70% dọc
    $maxW = $innerW * 0.86;
    $maxH = $innerH * 0.70;

    $lines  = $text_lines ?? [];
    $nLines = max(1, count($lines));

    // line-height phải khớp Service (1.24)
    $lineHpt = $fontPx * 1.24;
    $lineHmm = $lineHpt * 0.352778;

    // Tâm theo hộp chữ
    $tx = $innerX + $innerW/2;
    $ty = $innerY + $innerH/2 - ($nLines-1)*$lineHmm/2 + ($lineHmm*0.05);

    // Ước lượng bề rộng 1 dòng (mm) — cùng công thức Service
    $estimateLineWidthMm = function(string $ln, int $fontPt) {
      $chars = max(1, mb_strlen($ln));
      $mmPerEm = 0.352778 * $fontPt;
      $avgChar = 0.52; // chữ Việt có dấu
      return $chars * $avgChar * $mmPerEm;
    };
  @endphp

  <g font-family={{ $font_family }} font-size="{{ (int)$fontPx }}pt" fill="{{ $text }}"
     text-anchor="middle">
    @if(empty($lines))
      <text x="{{ $tx }}" y="{{ $ty }}" dominant-baseline="middle"> </text>
    @else
      <text x="{{ $tx }}" y="{{ $ty }}" dominant-baseline="middle">
        @foreach($lines as $i => $ln)
          @php
            $w = $estimateLineWidthMm($ln, (int)$fontPx);
            $useClamp = $w > $maxW;               // nếu dài quá, ép vào maxW
            $dy = $i === 0 ? 0 : $lineHmm;        // pt -> mm
          @endphp
          @if($useClamp)
            <tspan x="{{ $tx }}" dy="{{ number_format($dy, 2, '.', '') }}"
                   lengthAdjust="spacingAndGlyphs"
                   textLength="{{ number_format($maxW, 2, '.', '') }}">{{ $ln }}</tspan>
          @else
            <tspan x="{{ $tx }}" dy="{{ number_format($dy, 2, '.', '') }}">{{ $ln }}</tspan>
          @endif
        @endforeach
      </text>
    @endif
  </g>

</svg>
