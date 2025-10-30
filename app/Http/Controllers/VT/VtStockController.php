<?php

namespace App\Http\Controllers\VT;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Services\VT\VtLedgerService;
use Illuminate\Http\Request;

class VtStockController extends Controller
{
    public function __construct(private VtLedgerService $ledger) {}

    public function index(Request $request)
    {
        // Filters: q, loai(ASSET|CONSUMABLE), per_page, page
        $data = $this->ledger->listStocks($request->all());
        return CustomResponse::success($data);
    }
}
