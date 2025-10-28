<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Hóa Đơn #{{ $donHang->id }}</title>

  @php
    $company = [
      'name'  => 'PHÁT HOÀNG GIA FLORAL & DECOR',
      'addr'  => 'Địa chỉ: 100 Nguyễn Minh Hoàng, Phường Bảy Hiền, TP. Hồ Chí Minh',
      'phone' => 'Điện thoại: 0949 40 43 44',
      'email' => 'Email: info@phathoanggia.com.vn',
      'logo'  => asset('storage/logo.png'),
    ];
  @endphp

  <style>
    :root{ --primary:#f8a8c8; }
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'DejaVu Sans',Arial,sans-serif;font-size:14px;line-height:1.55;color:#333;background:#fff}
    .container{max-width:800px;margin:0 auto;padding:14px 18px}

    /* ===== HEADER ===== */
    .header{border-bottom:3px solid var(--primary);padding-bottom:10px;margin-bottom:14px}
    .header-top{display:flex;align-items:flex-start;gap:14px}
    .logo{width:92px;height:92px;object-fit:contain;border:1px solid #eee;border-radius:6px;background:#fff;flex:0 0 92px}
    .company-name{font-size:24px;font-weight:800;color:var(--primary);line-height:1.04;margin:0}
    .company-line{font-size:13.5px;line-height:1.12;margin:0}
    .invoice-title{text-align:center;font-size:28px;font-weight:700;color:#333;margin-top:6px}

    /* ===== INFO ROW: 3 CỘT NGANG ===== */
    .invoice-info{
      display:flex;gap:14px;margin-bottom:16px;flex-wrap:wrap;
    }
    .info-col{
      flex:1 1 0;       /* chia đều 3 cột */
      min-width:0;      /* tránh tràn */
      border:1px solid #eee;
      border-radius:6px;
      padding:10px;
    }
    .info-col h3{
      color:#333;background:rgba(248,168,200,.25);
      margin:-10px -10px 8px;padding:8px 10px;border-radius:6px 6px 0 0;
      font-size:15px;font-weight:700;
    }
    .detail-row{display:flex;gap:6px;margin-bottom:4px}
    .label{font-weight:700;flex:0 0 125px}
    .value{flex:1 1 auto;min-width:0}

    /* Khi màn rất hẹp (<768px) thì tự xuống 2 cột/1 cột */
    @media (max-width: 768px){
      .info-col{flex:1 1 100%}
    }

    /* ===== TABLE ===== */
    table{width:100%;border-collapse:collapse;margin:14px 0;table-layout:fixed}
    col.col-stt{width:6%} col.col-name{width:46%} col.col-dvt{width:12%}
    col.col-qty{width:10%} col.col-price{width:13%} col.col-amount{width:13%}
    th{background:var(--primary);color:#fff;padding:10px 8px;text-align:left;font-weight:700}
    td{padding:9px 8px;border-bottom:1px solid #ddd;vertical-align:top}
    tbody tr:nth-child(even){background:#f8f9fa}
    .text-right{text-align:right}.text-center{text-align:center}
    td,th{word-break:break-word}

    /* ===== TOTAL ===== */
    .total-section{margin-top:18px;border-top:2px solid var(--primary);padding-top:12px}
    .total-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee}
    .total-row.final{font-size:18px;font-weight:700;color:var(--primary);border-bottom:3px solid var(--primary)}
    .payment-status{margin-top:14px;padding:12px;border-radius:6px;text-align:center;font-weight:700}
    .payment-paid{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .payment-unpaid{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}

    /* ===== KÝ & FOOTER ===== */
    .signature-section{margin-top:26px;display:flex;justify-content:space-between}
    .signature-box{text-align:center;width:30%}
    .signature-line{border-top:1px solid #333;margin-top:38px;padding-top:5px;font-style:italic}
    .footer{margin-top:26px;text-align:center;color:#666;font-size:12px}

    /* ===== PRINT ===== */
@media print{
  .print-controls{display:none!important}
  *{-webkit-print-color-adjust:exact!important;color-adjust:exact!important}
  body{margin:0!important;padding:0!important;font-size:11px!important;line-height:1.35!important;color:#000!important;background:#fff!important}
  .container{max-width:none!important;margin:0 auto!important;padding:8px 10px!important}
  .header{border-bottom:2px solid #000!important;padding-bottom:8px!important;margin-bottom:10px!important}
  .company-name{font-size:18px!important;color:#000!important}
  .invoice-title{font-size:20px!important;color:#000!important;margin-top:4px!important}

  /* ==== GIỮ 3 CỘT TRÊN 1 HÀNG KHI IN ==== */
  .invoice-info{display:flex!important;flex-wrap:nowrap!important;gap:6px!important;margin-bottom:8px!important}
  .info-col{
    flex:0 0 33.333%!important;
    max-width:33.333%!important;
    border:1px solid #000!important;
    padding:6px!important;
    break-inside:avoid!important;   /* tránh vỡ cột khi in */
  }
  .info-col h3{
    background:#000!important;color:#fff!important;
    font-size:12px!important;margin:-6px -6px 6px!important;padding:4px 6px!important
  }
  .detail-row{margin-bottom:2px!important}
  .label{flex:0 0 85px!important;font-size:10px!important}
  .value{font-size:10px!important}

  /* Bảng */
  table{margin:10px 0!important;font-size:10px!important;table-layout:fixed!important;width:100%!important}
  th{background:#000!important;color:#fff!important;padding:5px 4px!important;border:1px solid #000!important}
  td{padding:5px 4px!important;border-bottom:1px solid #000!important;border-left:1px solid #000!important;border-right:1px solid #000!important;height:22px!important}

  .total-section{margin-top:8px!important;border-top:2px solid #000!important;padding-top:8px!important}
  .total-row{padding:3px 0!important;font-size:10px!important}
  .total-row.final{font-size:12px!important;color:#000!important;border-bottom:2px solid #000!important}
  .payment-status{margin:8px 0!important;padding:8px!important;border:2px solid #000!important;background:#fff!important;color:#000!important}

  .signature-section{margin-top:12px!important;font-size:9px!important}
  .signature-line{margin-top:24px!important;padding-top:3px!important}
  .footer{margin-top:10px!important;font-size:8px!important;color:#000!important}

  @page{size:A4 portrait;margin:0.3cm 0.5cm}
  .container,table,.total-section{page-break-inside:avoid}
}

  </style>
</head>
<body>
  <!-- Print Controls -->
  <div id="printControls" class="print-controls"
       style="position:fixed;top:10px;right:10px;z-index:1000;background:#fff;padding:10px;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,.1);display:none;">
    <button onclick="window.print()" style="background:var(--primary);color:#fff;border:none;padding:8px 15px;border-radius:3px;margin-right:5px;cursor:pointer;">🖨️ In hóa đơn</button>
    <button onclick="downloadPDF()" style="background:#28a745;color:#fff;border:none;padding:8px 15px;border-radius:3px;margin-right:5px;cursor:pointer;">📄 Tải PDF</button>
    <button onclick="closePrint()" style="background:#6c757d;color:#fff;border:none;padding:8px 15px;border-radius:3px;cursor:pointer;">✖️ Đóng</button>
  </div>

  <script>
    window.addEventListener('load',function(){
      document.getElementById('printControls').style.display='block';
      setTimeout(function(){window.print();},500);
    });
    function closePrint(){ if(window.opener){window.close();} else {window.history.back();} }
    function downloadPDF() {}
  </script>

  <div class="container">
    <!-- HEADER -->
    <div class="header">
      <div class="header-top">
        @if(!empty($company['logo']))
          <img class="logo" src="{{ $company['logo'] }}" alt="Logo">
        @else
          <div class="logo" style="border:1px dashed #ddd;"></div>
        @endif
        <div class="company">
          <div class="company-name">{{ $company['name'] }}</div>
          <p class="company-line">{{ $company['addr'] }}</p>
          <p class="company-line">{{ $company['phone'] }} | {{ $company['email'] }}</p>
        </div>
      </div>
      <div class="invoice-title">THÔNG TIN ĐƠN HÀNG</div>
    </div>

    <!-- INFO: 3 CỘT NGANG -->
    <div class="invoice-info">
      <!-- Cột 1: ĐƠN HÀNG -->
      <div class="info-col">
        <h3>Thông tin đơn hàng</h3>
        <div class="detail-row">
          <span class="label">Mã đơn hàng:</span>
          <span class="value">#{{ str_pad($donHang->ma_don_hang, 6, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="detail-row">
          <span class="label">Ngày tạo:</span>
          <span class="value">
            @php
              $createdAt = $donHang->created_at;
              if (is_string($createdAt) && strpos($createdAt, '/') !== false) {
                  echo substr($createdAt, 0, 16);
              } else {
                  echo \Carbon\Carbon::parse($createdAt)->format('d/m/Y H:i');
              }
            @endphp
          </span>
        </div>
        <div class="detail-row">
          <span class="label">Người bán:</span>
          <span class="value">{{ $donHang->nguoiTao->name ?? 'N/A' }}</span>
        </div>
      </div>

      <!-- Cột 2: KHÁCH HÀNG -->
      <div class="info-col">
        <h3>Thông tin khách hàng</h3>
        <div class="detail-row"><span class="label">Tên KH:</span><span class="value">{{ $donHang->ten_khach_hang ?? 'Khách lẻ' }}</span></div>
        <div class="detail-row"><span class="label">SĐT:</span><span class="value">{{ $donHang->so_dien_thoai ?? 'N/A' }}</span></div>
        <div class="detail-row"><span class="label">Địa chỉ giao:</span><span class="value">{{ $donHang->dia_chi_giao_hang ?? 'N/A' }}</span></div>
        <div class="detail-row"><span class="label">Ghi chú:</span><span class="value">{{ $donHang->ghi_chu ?? 'Không có' }}</span></div>
      </div>

      <!-- Cột 3: NGƯỜI NHẬN -->
      <div class="info-col">
        <h3>Thông tin người nhận</h3>
        <div class="detail-row"><span class="label">Tên người nhận:</span><span class="value">{{ $donHang->nguoi_nhan_ten ?? '—' }}</span></div>
        <div class="detail-row"><span class="label">SĐT người nhận:</span><span class="value">{{ $donHang->nguoi_nhan_sdt ?? '—' }}</span></div>
        <div class="detail-row">
          <span class="label">Ngày giờ nhận:</span>
          <span class="value">
            @php
              $tgn = $donHang->nguoi_nhan_thoi_gian ?? null;
              if (empty($tgn)) {
                echo '—';
              } else {
                try {
                  $tgnStr = is_string($tgn) ? $tgn : \Carbon\Carbon::parse($tgn)->toDateTimeString();
                  echo \Carbon\Carbon::parse($tgnStr)->format('d/m/Y H:i');
                } catch (\Throwable $e) { echo '—'; }
              }
            @endphp
          </span>
        </div>
      </div>
    </div>

    <!-- BẢNG SẢN PHẨM -->
    <table>
      <colgroup>
        <col class="col-stt"><col class="col-name"><col class="col-dvt">
        <col class="col-qty"><col class="col-price"><col class="col-amount">
      </colgroup>
      <thead>
        <tr>
          <th class="text-center">STT</th>
          <th>Tên sản phẩm</th>
          <th>Đơn vị tính</th>
          <th class="text-center">Số lượng</th>
          <th class="text-right">Đơn giá</th>
          <th class="text-right">Thành tiền</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($donHang->chiTietDonHangs as $index => $chiTiet)
          <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>{{ $chiTiet->sanPham->ten_san_pham ?? 'N/A' }}</td>
            <td>{{ $chiTiet->donViTinh->ten_don_vi ?? 'N/A' }}</td>
            <td class="text-center">{{ number_format($chiTiet->so_luong) }}</td>
            <td class="text-right">{{ number_format($chiTiet->don_gia, 0, ',', '.') }}đ</td>
            <td class="text-right">{{ number_format($chiTiet->thanh_tien, 0, ',', '.') }}đ</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <!-- TỔNG KẾT -->
    @php
      $tongHang  = (int)($donHang->tong_tien_hang ?? 0);
      $giamGia   = (int)($donHang->giam_gia ?? 0);
      $chiPhi    = (int)($donHang->chi_phi ?? 0);
      $tongCanTT = (int)($donHang->tong_tien_can_thanh_toan ?? max(0, $tongHang - $giamGia + $chiPhi));
      $daTT      = (int)($donHang->so_tien_da_thanh_toan ?? 0);
      $conLai    = max(0, $tongCanTT - $daTT);
    @endphp

    <div class="total-section">
      <div class="total-row"><span>Tổng tiền hàng:</span><span>{{ number_format($tongHang, 0, ',', '.') }}đ</span></div>
      <div class="total-row"><span>Giảm giá:</span><span>-{{ number_format($giamGia, 0, ',', '.') }}đ</span></div>
      <div class="total-row"><span>Chi phí khác:</span><span>{{ number_format($chiPhi, 0, ',', '.') }}đ</span></div>
      <div class="total-row final"><span>Tổng tiền cần thanh toán:</span><span>{{ number_format($tongCanTT, 0, ',', '.') }}đ</span></div>
      <div class="total-row"><span>Số tiền đã thanh toán:</span><span>{{ number_format($daTT, 0, ',', '.') }}đ</span></div>
      <div class="total-row"><span>Còn lại:</span><span>{{ number_format($conLai, 0, ',', '.') }}đ</span></div>
    </div>

    <!-- TRẠNG THÁI THANH TOÁN -->
    <div class="payment-status {{ $conLai === 0 ? 'payment-paid' : 'payment-unpaid' }}">
      {{ $conLai === 0 ? 'ĐÃ THANH TOÁN ĐỦ' : 'CHƯA THANH TOÁN ĐỦ' }}
    </div>

    <!-- CHỮ KÝ -->
    <div class="signature-section">
      <div class="signature-box"><div>Khách hàng</div><div class="signature-line">(Ký, ghi rõ họ tên)</div></div>
      <div class="signature-box"><div>Người bán hàng</div><div class="signature-line">(Ký, ghi rõ họ tên)</div></div>
      <div class="signature-box"><div>Thủ kho</div><div class="signature-line">(Ký, ghi rõ họ tên)</div></div>
    </div>

    <div class="footer">
      <p>Cảm ơn quý khách đã mua hàng!</p>
      <p>Hóa đơn được in vào lúc: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
  </div>
</body>
</html>
