<?php

namespace App\Modules\CongNoKH;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Class\CustomResponse;
use Illuminate\Support\Facades\Response;

class CongNoKhController extends Controller
{
    protected CongNoKhService $svc;

    public function __construct(CongNoKhService $svc)
    {
        $this->svc = $svc;
    }

    /**
     * GET /api/cong-no/summary
     * Tổng hợp công nợ theo khách (paging)
     */
    public function summary(Request $request)
    {
        $data = $this->svc->summary($request->all());
        return CustomResponse::success($data);
    }

    /**
     * GET /api/cong-no/customers/{id}
     * Danh sách các đơn còn nợ của 1 khách (paging)
     */
    public function byCustomer(Request $request, int $id)
    {
        $data = $this->svc->byCustomer($id, $request->all());
        return CustomResponse::success($data);
    }

    /**
     * GET /api/cong-no/export
     * Xuất CSV nhanh (an toàn, không cần thêm Export class).
     * Nếu bạn muốn Excel xlsx sau này, mình sẽ tạo Export class riêng (file mới).
     */
    public function export(Request $request)
    {
        $rows = $this->svc->summaryAll($request->all());

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cong_no_khach_hang.csv"',
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM cho Excel Windows
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($out, [
                'KhachHangID','TenKhachHang','SoDienThoai',
                'TongPhaiThu','DaThu','ConLai','SoDonConNo',
                'Age_0_30','Age_31_60','Age_61_90','Age_91_plus'
            ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->khach_hang_id,
                    $r->ten_khach_hang,
                    $r->so_dien_thoai,
                    $r->tong_phai_thu,
                    $r->da_thu,
                    $r->con_lai,
                    $r->so_don_con_no,
                    $r->age_0_30,
                    $r->age_31_60,
                    $r->age_61_90,
                    $r->age_91_plus,
                ]);
            }

            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }
}
