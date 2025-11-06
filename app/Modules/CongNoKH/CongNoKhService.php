<?php

namespace App\Modules\CongNoKH;

use Illuminate\Support\Facades\DB;

class CongNoKhService
{
    /**
     * Danh sách tổng hợp công nợ theo KH (đọc từ v_receivables_by_customer)
     * Hỗ trợ filter: q (tên KH/SĐT), min/max (con_lai), paging.
     */
    public function summary(array $params = []): array
    {
        $q       = trim((string)($params['q'] ?? ''));
        $min     = isset($params['min']) ? (int)$params['min'] : null;
        $max     = isset($params['max']) ? (int)$params['max'] : null;
        $page    = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, (int)($params['per_page'] ?? (int)env('PER_PAGE', 20)));

        $builder = DB::table('v_receivables_by_customer')->select([
            'khach_hang_id',
            'ten_khach_hang',
            'so_dien_thoai',
            'tong_phai_thu',
            'da_thu',
            'con_lai',
            'so_don_con_no',
            'age_0_30',
            'age_31_60',
            'age_61_90',
            'age_91_plus',
        ]);

        if ($q !== '') {
            $builder->where(function ($w) use ($q) {
                $w->where('ten_khach_hang', 'like', "%{$q}%")
                  ->orWhere('so_dien_thoai', 'like', "%{$q}%")
                  ->orWhere('khach_hang_id', $q);
            });
        }

        if ($min !== null) {
            $builder->where('con_lai', '>=', $min);
        }
        if ($max !== null) {
            $builder->where('con_lai', '<=', $max);
        }

        // Ưu tiên KH còn nợ lớn nhất
        $builder->orderByDesc('con_lai')->orderBy('khach_hang_id');

        $total = (clone $builder)->count();
        $rows  = $builder->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return [
            'collection' => $rows,
            'total'      => $total,
            'pagination' => [
                'current_page'   => $page,
                'per_page'       => $perPage,
                'last_page'      => (int)ceil($total / $perPage),
                'from'           => ($total === 0) ? 0 : (($page - 1) * $perPage + 1),
                'to'             => min($page * $perPage, $total),
                'total_current'  => count($rows),
            ],
        ];
    }

    /**
     * Danh sách các ĐƠN còn nợ của 1 khách (đọc từ v_receivables_by_order)
     * Hỗ trợ filter: from/to (ngay_tao_don_hang), paging.
     */
    public function byCustomer(int $khachHangId, array $params = []): array
    {
        $from    = $params['from'] ?? null;
        $to      = $params['to']   ?? null;
        $page    = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, (int)($params['per_page'] ?? (int)env('PER_PAGE', 20)));

        $builder = DB::table('v_receivables_by_order')->select([
            'don_hang_id',
            'ma_don_hang',
            'khach_hang_id',
            'ten_khach_hang',
            'so_dien_thoai',
            'tong_phai_thu',
            'da_thu',
            'du_no',
            'trang_thai_thanh_toan',
            'trang_thai_don_hang',
            'ngay_tao_don_hang',
        ])->where('khach_hang_id', $khachHangId);

        if (!empty($from)) {
            $builder->whereDate('ngay_tao_don_hang', '>=', $from);
        }
        if (!empty($to)) {
            $builder->whereDate('ngay_tao_don_hang', '<=', $to);
        }

        // Mới nhất trước
        $builder->orderByDesc('ngay_tao_don_hang')->orderByDesc('don_hang_id');

        $total = (clone $builder)->count();
        $rows  = $builder->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return [
            'collection' => $rows,
            'total'      => $total,
            'pagination' => [
                'current_page'   => $page,
                'per_page'       => $perPage,
                'last_page'      => (int)ceil($total / $perPage),
                'from'           => ($total === 0) ? 0 : (($page - 1) * $perPage + 1),
                'to'             => min($page * $perPage, $total),
                'total_current'  => count($rows),
            ],
        ];
    }

    /**
     * Lấy toàn bộ dữ liệu summary (phục vụ export CSV/Excel)
     * Cảnh báo: không phân trang.
     */
    public function summaryAll(array $params = []): array
    {
        $params = array_merge($params, ['page' => 1, 'per_page' => PHP_INT_MAX]);
        $paged  = $this->summary($params);
        return $paged['collection']->toArray();
    }
}
