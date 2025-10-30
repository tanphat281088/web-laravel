<?php

namespace App\Http\Controllers\VT;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Services\VT\VtLedgerService;
use Illuminate\Http\Request;

class VtLedgerController extends Controller
{
    public function __construct(private VtLedgerService $ledger) {}

    public function index(Request $request)
    {
        // Filters: vt_item_id, from, to, loai_ct, q, per_page, page
        $data = $this->ledger->listLedger($request->all());
        return CustomResponse::success($data);
    }
}
