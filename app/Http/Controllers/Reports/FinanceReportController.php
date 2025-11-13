<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\FinanceReportService;
use App\Class\CustomResponse;

class FinanceReportController extends Controller
{
    public function __construct(
        protected FinanceReportService $svc
    ) {}

    /**
     * GET /bao-cao-quan-tri/tai-chinh/summary
     * Trả về KPI + chỉ số nâng cao (đọc-only).
     */
    public function summary(Request $req)
    {
        $from = $req->query('from');
        $to   = $req->query('to');

        $data = $this->svc->summary($from, $to);

        return CustomResponse::success($data);
    }

    /**
     * GET /bao-cao-quan-tri/tai-chinh/receivables
     * Danh sách công nợ KH (tổng hợp theo khách) + aging (paging).
     */
    public function receivables(Request $req)
    {
        $q     = trim((string) $req->query('q', ''));
        $from  = $req->query('from');
        $to    = $req->query('to');
        $page  = max(1, (int) $req->query('page', 1));
        $per   = max(1, min(200, (int) $req->query('per_page', 25)));

        $data = $this->svc->receivables($q, $from, $to, $page, $per);

        return CustomResponse::success($data);
    }

    /**
     * GET /bao-cao-quan-tri/tai-chinh/orders
     * Danh sách đơn hàng trong kỳ (paging).
     */
    public function orders(Request $req)
    {
        $q     = trim((string) $req->query('q', ''));
        $from  = $req->query('from');
        $to    = $req->query('to');
        $page  = max(1, (int) $req->query('page', 1));
        $per   = max(1, min(200, (int) $req->query('per_page', 25)));

        $data = $this->svc->orders($q, $from, $to, $page, $per);

        return CustomResponse::success($data);
    }

    /**
     * GET /bao-cao-quan-tri/tai-chinh/receipts
     * Danh sách phiếu thu (paging).
     */
    public function receipts(Request $req)
    {
        $q     = trim((string) $req->query('q', ''));
        $from  = $req->query('from');
        $to    = $req->query('to');
        $page  = max(1, (int) $req->query('page', 1));
        $per   = max(1, min(200, (int) $req->query('per_page', 25)));

        $data = $this->svc->receipts($q, $from, $to, $page, $per);

        return CustomResponse::success($data);
    }

    /**
     * GET /bao-cao-quan-tri/tai-chinh/payments
     * Danh sách phiếu chi (paging).
     */
    public function payments(Request $req)
    {
        $q     = trim((string) $req->query('q', ''));
        $from  = $req->query('from');
        $to    = $req->query('to');
        $page  = max(1, (int) $req->query('page', 1));
        $per   = max(1, min(200, (int) $req->query('per_page', 25)));

        $data = $this->svc->payments($q, $from, $to, $page, $per);

        return CustomResponse::success($data);
    }

    /**
     * GET /bao-cao-quan-tri/tai-chinh/ledger
     * Sổ quỹ theo tài khoản (paging).
     */
    public function ledger(Request $req)
    {
        $accountId = $req->query('tai_khoan_id');
        $from      = $req->query('from');
        $to        = $req->query('to');
        $page      = max(1, (int) $req->query('page', 1));
        $per       = max(1, min(200, (int) $req->query('per_page', 25)));
        $q         = trim((string) $req->query('q', ''));

        $data = $this->svc->ledger($accountId, $q, $from, $to, $page, $per);

        return CustomResponse::success($data);
    }
}
