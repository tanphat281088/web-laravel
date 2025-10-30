<?php
// config/thu_chi.php
return [
    // Cách đồng bộ phiếu thu cho đơn:
    // 'adjustment' = SINH PHIẾU ĐIỀU CHỈNH (có thể âm/dương), KHÔNG sửa lịch sử (khuyên dùng)
    // 'update'     = cập nhật phiếu auto gần nhất (ít dùng, kém audit)
    'auto_receipt_mode'   => 'adjustment',

    // Nhãn/ghi chú chuẩn
    'auto_receipt_reason' => 'Thu tự động theo đơn hàng',
    'adjustment_reason'   => 'Hiệu chỉnh theo thay đổi đơn hàng',

        // Mặc định: 1=Tiền mặt, 2=Chuyển khoản
    'auto_receipt_payment_method' => env('AUTO_RECEIPT_PAYMENT_METHOD', 2),
    // ID tài khoản tiền nhận mặc định nếu là chuyển khoản (VD: ID của VÕ THỊ ÁNH TUYẾT)
    'auto_receipt_account_id'     => env('AUTO_RECEIPT_ACCOUNT_ID', null),


    // Ghi chú: Service sẽ tạo "idempotent key" dựa (don_hang_id, updated_at, delta)
    // để tránh sinh trùng phiếu khi retry.
];
