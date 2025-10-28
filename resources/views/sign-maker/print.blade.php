@php
  /**
   * Khổ giấy & layout lưới
   * Dompdf hiểu trực tiếp size: A3/A4/A5/A6/A7 (portrait)
   * Mặc định margin 10mm
   */
  $paper   = strtoupper($paper ?? 'A4');
  $margin  = 10; // mm

  // Kích thước trang (mm) để tính cột & co giãn cell
  $dims = match($paper) {
    'A3' => ['w' => 297.0, 'h' => 420.0],
    'A4' => ['w' => 210.0, 'h' => 297.0],
    'A5' => ['w' => 148.0, 'h' => 210.0],
    'A6' => ['w' => 105.0, 'h' => 148.0],
    'A7' => ['w' =>  74.0, 'h' => 105.0],
    default => ['w' => 210.0, 'h' => 297.0],
  };

  // Khoảng cách & số cột đề xuất theo khổ giấy
  // (có thể tinh chỉnh thêm tuỳ template thực tế)
  [$cols, $gap] = match($paper) {
    'A3' => [3, 6],
    'A4' => [2, 6],
    'A5' => [2, 5],
    'A6' => [2, 4],
    'A7' => [1, 4],
    default => [2, 6],
  };

  // Bề rộng tối đa cho 1 cell theo số cột (đã trừ margin + gap đôi bên)
  $maxCellW = ($dims['w'] - 2*$margin - $cols * 2 * $gap) / $cols;

  // Helper co giãn topper theo cột
  $calcSize = function(array $it) use ($maxCellW) {
      $w = ($it['tpl']->width_mm ?? 0) + 2 * ($it['bleed_mm'] ?? 0);
      $h = ($it['tpl']->height_mm ?? 0) + 2 * ($it['bleed_mm'] ?? 0);
      if ($w <= 0 || $h <= 0) return ['w' => 60.0, 'h' => 40.0, 'scale' => 1.0];

      $scale = $w > 0 ? min(1.0, $maxCellW / $w) : 1.0;
      return [
        'w' => round($w * $scale, 2),
        'h' => round($h * $scale, 2),
        'scale' => $scale,
      ];
  };
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<style>
  @page { size: {{ $paper }} portrait; margin: {{ $margin }}mm; }
  /* DejaVu Sans có sẵn trong dompdf, hiển thị tốt tiếng Việt */
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; }

  .grid { font-size: 0; } /* khử khoảng trắng inline-block */
  .cell {
    display: inline-block;
    vertical-align: top;
    margin: {{ $gap }}mm;
    page-break-inside: avoid;
    position: relative;
    background: transparent;
  }

  /* crop mark đơn giản ở 4 góc mỗi cell (mô phỏng) */
  .cell:before, .cell:after { content:""; position:absolute; background:#000; }
  /* ngang trái trên */
  .cell:before { left:-3mm; top:-3mm; width:8mm; height:0.2mm; }
  /* dọc phải dưới */
  .cell:after  { right:-3mm; bottom:-3mm; width:0.2mm; height:8mm; }

  /* Vùng nội dung topper (nhúng HTML/SVG của partial) */
  .content {
    width: 100%;
    height: 100%;
    overflow: hidden;
  }
</style>
</head>
<body>
  <div class="grid">
    @foreach($items as $it)
      @php
        $sz = $calcSize($it); // ['w','h','scale']
      @endphp
      <div class="cell" style="width:{{ $sz['w'] }}mm; height:{{ $sz['h'] }}mm;">
        <div class="content">
          {{-- $it đã chứa đủ keys từ SignRenderService::buildViewData --}}
          @include('sign-maker.partials.single', $it)
        </div>
      </div>
    @endforeach
  </div>
</body>
</html>
