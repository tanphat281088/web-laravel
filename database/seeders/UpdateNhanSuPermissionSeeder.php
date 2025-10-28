<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateNhanSuPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy toàn bộ vai trò đang hoạt động
        $roles = DB::table('vai_tros')->where('trang_thai', 1)->get();

        DB::beginTransaction();
        try {
            foreach ($roles as $role) {
                $json = $role->phan_quyen ?: '[]';
                $arr  = json_decode($json, true);
                if (!is_array($arr)) {
                    $arr = [];
                }

                // Tìm item 'nhan-su'
                $idx = -1;
                foreach ($arr as $i => $item) {
                    if (is_array($item) && ($item['name'] ?? null) === 'nhan-su') {
                        $idx = $i;
                        break;
                    }
                }

                // Bật tối thiểu các quyền cần thiết cho HR
                // Bổ sung 'list' (GET list), 'store' (POST create), 'show' (GET /.../me), và 'update' (PATCH duyệt/từ chối/hủy)
                $actions = [
                    'showMenu' => true, // hiện nhóm menu HR
                    'index'    => true, // xem danh sách/lịch sử (GET)
                    'list'     => true, // alias phổ biến cho GET list
                    'show'     => true, // một số route view-detail/me map sang show
                    'create'   => true, // tạo/checkin/checkout (POST)
                    'store'    => true, // alias phổ biến cho POST create
                    'update'   => true, // PATCH approve/reject/cancel đơn từ
                    'export'   => true, // tải dữ liệu (nếu có)
                ];

                if ($idx === -1) {
                    // Chưa có mục 'nhan-su' -> thêm mới
                    $arr[] = [
                        'name'    => 'nhan-su',
                        'actions' => $actions,
                    ];
                } else {
                    // Đã có -> hợp nhất, chỉ BỔ SUNG cờ còn thiếu (không xóa cũ)
                    $current = (array) ($arr[$idx]['actions'] ?? []);
                    $arr[$idx]['actions'] = array_merge($current, $actions);
                }

                DB::table('vai_tros')
                    ->where('id', $role->id)
                    ->update([
                        'phan_quyen' => json_encode($arr, JSON_UNESCAPED_UNICODE),
                    ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
