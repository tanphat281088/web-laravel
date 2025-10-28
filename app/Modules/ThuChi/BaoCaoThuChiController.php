<?php

namespace App\Modules\ThuChi;

use Illuminate\Http\Request;
use App\Class\CustomResponse;

class BaoCaoThuChiController
{
    public function __construct(
        protected BaoCaoThuChiService $service = new BaoCaoThuChiService()
    ) {}

    /**
     * GET /thu-chi/bao-cao/tong-hop?from=YYYY-MM-DD&to=YYYY-MM-DD
     * hoặc /thu-chi/bao-cao/tong-hop?preset=week|month
     */
    public function tongHop(Request $request)
    {
        try {
            $data = $this->service->tongHop($request->all());
            // Trả về theo format chung của hệ thống
            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {
            return CustomResponse::error($e->getMessage());
        }
    }
}
