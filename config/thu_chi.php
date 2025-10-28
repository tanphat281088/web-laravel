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

    // Ghi chú: Service sẽ tạo "idempotent key" dựa (don_hang_id, updated_at, delta)
    // để tránh sinh trùng phiếu khi retry.
];
