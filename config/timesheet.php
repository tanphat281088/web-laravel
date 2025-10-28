<?php

return [
    // BẬT/TẮT các tính năng nâng cao. false = giữ logic cũ (an toàn).
    'enabled' => env('TIMESHEET_ENABLED', false),

    // ===== NEW: Kỳ công bắt đầu vào ngày mấy trong tháng (6 = từ ngày 6 đến ngày 5 tháng sau)
    'cycle_start_day' => (int) env('TIMESHEET_CYCLE_START_DAY', 6),

    // OT: chỉ tính phần sau giờ kết thúc (an toàn, không tính đến trước giờ bắt đầu)
    'ot' => [
        'enabled'           => env('TIMESHEET_OT_ENABLED', false),
        'after_end_only'    => env('TIMESHEET_OT_AFTER_END_ONLY', true), // true = chỉ tính OT sau giờ kết thúc
        // ngưỡng tối thiểu để tính OT (phút) — tránh OT lặt vặt 1–2 phút
        'min_minutes'       => (int) env('TIMESHEET_OT_MIN_MINUTES', 10),
    ],

    // Giờ làm việc mặc định ngày thường (Thứ 2..Thứ 6)
    'workday' => [
        'start' => env('TIMESHEET_START', '08:30'),   // HH:MM
        'end'   => env('TIMESHEET_END',   '17:30'),   // HH:MM
        'break' => [
            'start' => env('TIMESHEET_BREAK_START', '12:00'),
            'end'   => env('TIMESHEET_BREAK_END',   '13:30'), // yêu cầu: 12:00–13:30
        ],
        'grace_minutes' => (int) env('TIMESHEET_GRACE_MINUTES', 5), // phút “trễ cho phép”
    ],

    // Weekend/Holiday
    'calendar' => [
        'weekend' => [
            'enabled' => env('TIMESHEET_WEEKEND_ENABLED', false), // bật xử lý cuối tuần
            // 1 = Monday ... 7 = Sunday; mặc định: 6,7 (Thứ 7, CN)
            'days'    => explode(',', env('TIMESHEET_WEEKEND_DAYS', '6,7')),
            // nếu true: KHÔNG tính ngày công cho cuối tuần, nhưng vẫn cộng giờ công/OT
            'exclude_from_worked_days' => env('TIMESHEET_WEEKEND_EXCLUDE_FROM_DAYS', true),
        ],
        'holiday' => [
            'enabled' => env('TIMESHEET_HOLIDAY_ENABLED', false), // bật xử lý ngày lễ
            // Danh sách ngày lễ cố định (YYYY-MM-DD), có thể load từ DB sau
            'list'    => array_filter(array_map('trim', explode(',', env('TIMESHEET_HOLIDAYS', '')))),
            // nếu true: KHÔNG tính ngày công cho ngày lễ, nhưng vẫn cộng giờ công/OT
            'exclude_from_worked_days' => env('TIMESHEET_HOLIDAY_EXCLUDE_FROM_DAYS', true),
        ],
    ],
];
