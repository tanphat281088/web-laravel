<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa Đơn #{{ $donHang->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
        }

        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .invoice-details,
        .customer-details {
            width: 48%;
        }

        .invoice-details h3,
        .customer-details h3 {
            color: #007bff;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .detail-row {
            margin-bottom: 5px;
        }

        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .table th {
            background-color: #007bff;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
        }

        .table td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-section {
            margin-top: 30px;
            border-top: 2px solid #007bff;
            padding-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .total-row.final {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            border-bottom: 3px solid #007bff;
        }

        .payment-status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .payment-paid {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .payment-unpaid {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 30%;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 5px;
            font-style: italic;
        }
    </style>
</head>

<body>
    <!-- Print Controls -->
    <div class="print-controls"
        style="position: fixed; top: 10px; right: 10px; z-index: 1000; background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: none;"
        id="printControls">
        <button onclick="window.print()"
            style="background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 3px; margin-right: 5px; cursor: pointer;">🖨️
            In hóa đơn</button>
        <button onclick="downloadPDF()"
            style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 3px; margin-right: 5px; cursor: pointer;">📄
            Tải PDF</button>
        <button onclick="closePrint()"
            style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 3px; cursor: pointer;">✖️
            Đóng</button>
    </div>

    <script>
        // Tự động trigger in khi trang load
        window.addEventListener('load', function() {
            // Hiển thị controls
            document.getElementById('printControls').style.display = 'block';

            // Delay một chút để trang load hoàn toàn rồi mới trigger print
            setTimeout(function() {
                // Tự động mở print dialog
                window.print();
            }, 500);
        });

        // Function đóng cửa sổ
        function closePrint() {
            // Nếu là popup/tab mới thì đóng
            if (window.opener) {
                window.close();
            } else {
                // Nếu không thì về trang trước
                window.history.back();
            }
        }

        // CSS cho print media - Tối ưu cho 1 trang A4, đen trắng
        const printStyles = `
            @media print {
                .print-controls {
                    display: none !important;
                }
                
                /* Reset và tối ưu cho 1 trang */
                * {
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                
                                 body {
                     margin: 0 !important;
                     padding: 0 !important;
                     font-size: 11px !important;
                     line-height: 1.4 !important;
                     color: #000 !important;
                     background: #fff !important;
                     min-height: 100% !important;
                 }
                
                                 .container {
                     max-width: none !important;
                     margin: 0 auto !important;
                     padding: 8px 15px !important;
                     height: 98% !important;
                 }
                
                /* Header compact */
                .header {
                    border-bottom: 2px solid #000 !important;
                    padding-bottom: 8px !important;
                    margin-bottom: 12px !important;
                }
                
                .company-name {
                    font-size: 18px !important;
                    color: #000 !important;
                    margin-bottom: 3px !important;
                }
                
                .invoice-title {
                    font-size: 20px !important;
                    color: #000 !important;
                    margin: 8px 0 !important;
                }
                
                /* Invoice info compact */
                .invoice-info {
                    margin-bottom: 12px !important;
                }
                
                .invoice-details h3, .customer-details h3 {
                    color: #000 !important;
                    font-size: 13px !important;
                    margin-bottom: 5px !important;
                }
                
                .detail-row {
                    margin-bottom: 2px !important;
                    font-size: 10px !important;
                }
                
                .label {
                    width: 80px !important;
                    font-size: 10px !important;
                }
                
                                 /* Table compact */
                 .table {
                     margin: 12px 0 !important;
                     font-size: 10px !important;
                     width: 100% !important;
                     table-layout: fixed !important;
                 }
                 
                 .table th {
                     background-color: #000 !important;
                     color: #fff !important;
                     padding: 5px 4px !important;
                     font-size: 10px !important;
                     border: 1px solid #000 !important;
                 }
                 
                 .table td {
                     padding: 5px 4px !important;
                     border-bottom: 1px solid #000 !important;
                     border-left: 1px solid #000 !important;
                     border-right: 1px solid #000 !important;
                     font-size: 10px !important;
                     height: 22px !important;
                 }
                
                .table tbody tr:nth-child(even) {
                    background-color: #f5f5f5 !important;
                }
                
                                 /* Total section compact */
                 .total-section {
                     margin-top: 10px !important;
                     border-top: 2px solid #000 !important;
                     padding-top: 10px !important;
                 }
                 
                 .total-row {
                     padding: 3px 0 !important;
                     border-bottom: 1px solid #ccc !important;
                     font-size: 10px !important;
                     line-height: 1.5 !important;
                 }
                 
                 .total-row.final {
                     font-size: 12px !important;
                     color: #000 !important;
                     border-bottom: 2px solid #000 !important;
                     font-weight: bold !important;
                     padding: 5px 0 !important;
                 }
                
                                 /* Payment status compact */
                 .payment-status {
                     margin-top: 10px !important;
                     margin-bottom: 10px !important;
                     padding: 8px !important;
                     text-align: center !important;
                     font-weight: bold !important;
                     font-size: 11px !important;
                     border: 2px solid #000 !important;
                     background-color: #fff !important;
                     color: #000 !important;
                 }
                
                                 /* Signature section compact */
                 .signature-section {
                     margin-top: 15px !important;
                     font-size: 9px !important;
                     position: relative !important;
                     bottom: 0 !important;
                 }
                 
                 .signature-box {
                     width: 30% !important;
                 }
                 
                 .signature-line {
                     border-top: 1px solid #000 !important;
                     margin-top: 30px !important;
                     padding-top: 3px !important;
                 }
                
                                 /* Footer compact */
                 .footer {
                     margin-top: 12px !important;
                     font-size: 8px !important;
                     color: #000 !important;
                     position: relative !important;
                     bottom: 0 !important;
                 }
                
                                 /* Đảm bảo fit trong 1 trang A4 với chiều dài tăng thêm */
                 @page {
                     size: A4 portrait;
                     margin: 0.3cm 0.5cm;
                 }
                
                /* Ngăn ngừa page break */
                .container {
                    page-break-inside: avoid;
                }
                
                .table {
                    page-break-inside: avoid;
                }
                
                .total-section {
                    page-break-inside: avoid;
                }
            }
        `;

        // Thêm print styles
        const styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = printStyles;
        document.head.appendChild(styleSheet);
    </script>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">CÔNG TY ABC</div>
            <div>Địa chỉ: 123 Đường ABC, TP. Cần Thơ</div>
            <div>Điện thoại: 0123 456 789 | Email: info@abc.com</div>
            <div class="invoice-title">HÓA ĐƠN BÁN HÀNG</div>
        </div>

        <!-- Invoice Info -->
        <div class="invoice-info">
            <div class="invoice-details">
                <h3>Thông tin hóa đơn</h3>
                <div class="detail-row">
                    <span class="label">Mã hóa đơn:</span>
                    #{{ str_pad($donHang->ma_don_hang, 6, '0', STR_PAD_LEFT) }}
                </div>
                <div class="detail-row">
                    <span class="label">Ngày tạo:</span>
                    @php
                        // Xử lý datetime đã được format bởi DateTimeFormatter trait
                        $createdAt = $donHang->created_at;
                        if (strpos($createdAt, '/') !== false) {
                            // Nếu đã được format thành d/m/Y H:i:s thì hiển thị trực tiếp
                            echo substr($createdAt, 0, 16); // Lấy d/m/Y H:i
                        } else {
                            // Nếu chưa format thì dùng Carbon
                            echo \Carbon\Carbon::parse($createdAt)->format('d/m/Y H:i');
                        }
                    @endphp
                </div>
                <div class="detail-row">
                    <span class="label">Người bán:</span>
                    {{ $donHang->nguoiTao->name ?? 'N/A' }}
                </div>
            </div>

            <div class="customer-details">
                <h3>Thông tin khách hàng</h3>
                <div class="detail-row">
                    <span class="label">Tên khách hàng:</span>
                    {{ $donHang->ten_khach_hang ?? 'Khách lẻ' }}
                </div>
                <div class="detail-row">
                    <span class="label">Số điện thoại:</span>
                    {{ $donHang->so_dien_thoai ?? 'N/A' }}
                </div>
                <div class="detail-row">
                    <span class="label">Ghi chú:</span>
                    {{ $donHang->ghi_chu ?? 'Không có' }}
                </div>
            </div>
        </div>

        <!-- Product Table -->
        <table class="table">
            <thead>
                <tr>
                    <th>STT</th>
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

        <!-- Total Section -->
        <div class="total-section">
            <div class="total-row">
                <span>Tổng tiền hàng:</span>
                <span>{{ number_format($donHang->tong_tien_hang, 0, ',', '.') }}đ</span>
            </div>
            <div class="total-row">
                <span>Giảm giá:</span>
                <span>-{{ number_format($donHang->giam_gia, 0, ',', '.') }}đ</span>
            </div>
            <div class="total-row">
                <span>Chi phí khác:</span>
                <span>{{ number_format($donHang->chi_phi, 0, ',', '.') }}đ</span>
            </div>
            <div class="total-row final">
                <span>Tổng tiền cần thanh toán:</span>
                <span>{{ number_format($donHang->tong_tien_can_thanh_toan, 0, ',', '.') }}đ</span>
            </div>
            <div class="total-row">
                <span>Số tiền đã thanh toán:</span>
                <span>{{ number_format($donHang->so_tien_da_thanh_toan, 0, ',', '.') }}đ</span>
            </div>
            <div class="total-row">
                <span>Còn lại:</span>
                <span>{{ number_format($donHang->tong_tien_can_thanh_toan - $donHang->so_tien_da_thanh_toan, 0, ',', '.') }}đ</span>
            </div>
        </div>

        <!-- Payment Status -->
        <div class="payment-status {{ $donHang->trang_thai_thanh_toan ? 'payment-paid' : 'payment-unpaid' }}">
            {{ $donHang->trang_thai_thanh_toan ? 'ĐÃ THANH TOÁN ĐỦ' : 'CHƯA THANH TOÁN ĐỦ' }}
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div>Khách hàng</div>
                <div class="signature-line">(Ký, ghi rõ họ tên)</div>
            </div>
            <div class="signature-box">
                <div>Người bán hàng</div>
                <div class="signature-line">(Ký, ghi rõ họ tên)</div>
            </div>
            <div class="signature-box">
                <div>Thủ kho</div>
                <div class="signature-line">(Ký, ghi rõ họ tên)</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Cảm ơn quý khách đã mua hàng!</p>
            <p>Hóa đơn được in vào lúc: {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</body>

</html>
