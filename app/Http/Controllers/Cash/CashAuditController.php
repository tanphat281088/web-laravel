<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Class\CustomResponse;
use App\Services\Cash\CashAuditService;

class CashAuditController extends Controller
{
    public function __construct(
        protected CashAuditService $svc
    ) {}

    /**
     * GET /cash/audit-delta
     * Audit lệch giữa PHIẾU THU CK và SỔ QUỸ theo alias bank/account của tài khoản nhận.
     *
     * Query:
     * - tai_khoan_id (int, required)
     * - alias_bank (string, optional)   // ví dụ: "MB"
     * - alias_account (string, optional)// ví dụ: "0936692203"
     * - from (Y-m-d, optional)
     * - to   (Y-m-d, optional)
     * - debug (0|1, optional)           // bật trả thêm meta debug
     * - include_ok (0|1, optional)      // kèm cả dòng cân khớp vào top10 debug
     */
    public function audit(Request $req)
    {
        $req->validate([
            'tai_khoan_id'  => 'required|integer|exists:tai_khoan_tiens,id',
            'alias_bank'    => 'nullable|string|max:191',
            'alias_account' => 'nullable|string|max:191',
            'from'          => 'nullable|date',
            'to'            => 'nullable|date',
            'debug'         => 'nullable|in:0,1',
            'include_ok'    => 'nullable|in:0,1',
        ]);

        $data = $this->svc->auditReceiptsDelta(
            taiKhoanId:   (int)$req->get('tai_khoan_id'),
            aliasBank:    $req->get('alias_bank'),
            aliasAccount: $req->get('alias_account'),
            from:         $req->get('from'),
            to:           $req->get('to'),
            debug:        (bool)((int)$req->get('debug', 0) === 1),
            includeOk:    (bool)((int)$req->get('include_ok', 0) === 1),
        );

        return CustomResponse::success($data);
    }

    /**
     * POST /cash/audit-delta/fix
     * Áp dụng sửa idempotent (backfill thiếu và/hoặc điều chỉnh âm).
     *
     * Body (JSON or form-encoded):
     * - tai_khoan_id (int, required)
     * - alias_bank (string, optional)
     * - alias_account (string, optional)
     * - from (Y-m-d, optional)
     * - to   (Y-m-d, optional)
     * - scope ("missing"|"over"|"both", optional, default="both")
     * - dry_run (int: 0|1, optional, default=1)
     */
    public function fix(Request $req)
    {
        $req->validate([
            'tai_khoan_id'  => 'required|integer|exists:tai_khoan_tiens,id',
            'alias_bank'    => 'nullable|string|max:191',
            'alias_account' => 'nullable|string|max:191',
            'from'          => 'nullable|date',
            'to'            => 'nullable|date',
            'scope'         => 'nullable|in:missing,over,both',
            'dry_run'       => 'nullable|in:0,1',
        ]);

        $out = $this->svc->applyFix(
            taiKhoanId:   (int)$req->get('tai_khoan_id'),
            aliasBank:    $req->get('alias_bank'),
            aliasAccount: $req->get('alias_account'),
            from:         $req->get('from'),
            to:           $req->get('to'),
            scope:        $req->get('scope','both'),
            dryRun:       (bool)((int)$req->get('dry_run', 1) === 1),
            userId:       optional($req->user())->id ?? null,
        );

        return CustomResponse::success(
            $out,
            $out['dry_run'] ? 'DRY-RUN (không ghi dữ liệu)' : 'Đã áp dụng điều chỉnh'
        );
    }
}
