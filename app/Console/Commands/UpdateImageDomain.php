<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lệnh artisan để gọi
 *
 * Chạy bằng:
 *   php artisan app:domain
 *
 * Sau khi chạy sẽ:
 * 1. Hỏi người dùng nhập domain cũ (VD: http://127.0.0.1:8000/)
 * 2. Hỏi người dùng nhập domain mới (VD: http://khohang.mauwebsite.online/)
 * 3. Tự động loại bỏ dấu "/" ở cuối domain để tránh lỗi đường dẫn
 * 4. Hiển thị thông báo confirm:
 *      Domain cũ màu đỏ, domain mới màu xanh
 * 5. Nếu chọn "yes" thì hệ thống sẽ:
 *      - Update bảng `images`
 *      - Thay thế domain cũ bằng domain mới trong cột `path`
 *      - In ra số bản ghi đã được cập nhật
 *
 * Ví dụ kết quả hiển thị:
 *   Domain main cũ là gì:
 *   > http://127.0.0.1:8000/
 *
 *   Domain main mới là gì:
 *   > http://khohang.mauwebsite.online/
 *
 *   Bạn có chắc chắn muốn thay http://127.0.0.1:8000 thành http://khohang.mauwebsite.online không? (yes/no) [yes]:
 *   Đang thay http://127.0.0.1:8000 thành http://khohang.mauwebsite.online ...
 *   Đã cập nhật 39 bản ghi thành công.
 */

class UpdateImageDomain extends Command
{
    protected $signature = 'app:domain';

    protected $description = 'Cập nhật domain trong cột path của bảng images';

    public function handle()
    {
        // Hỏi domain và tự bỏ dấu "/" cuối
        $old = rtrim($this->ask('Domain main cũ là gì'), '/');
        $new = rtrim($this->ask('Domain main mới là gì'), '/');

        // In màu đỏ (old) và xanh lá (new)
        $oldColored = "<fg=red>$old</>";
        $newColored = "<fg=blue>$new</>";

        // Xác nhận
        if (! $this->confirm("Bạn có chắc chắn muốn thay {$oldColored} thành {$newColored} không?", true)) {
            $this->info("Đã huỷ thao tác.");
            return;
        }

        $this->line("Đang thay {$oldColored} thành {$newColored} ...");

        // Thực hiện update
        $affected = DB::table('images')
            ->where('path', 'like', "%$old%")
            ->update([
                'path' => DB::raw("REPLACE(path, '$old', '$new')")
            ]);

        $this->info("Đã cập nhật $affected bản ghi thành công.");
    }
}