<?php

namespace App\Http\Controllers\VT;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VtMasterController extends Controller
{
    public function categoryOptions(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $rows = DB::table('vt_categories')
            ->select('id', 'name')
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', '%'.$q.'%'))
            ->where('active', 1)
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn($r) => ['value' => $r->name, 'label' => $r->name]);

        return CustomResponse::success($rows);
    }

    public function groupOptions(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $rows = DB::table('vt_groups')
            ->select('id', 'name')
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', '%'.$q.'%'))
            ->where('active', 1)
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn($r) => ['value' => $r->name, 'label' => $r->name]);

        return CustomResponse::success($rows);
    }

    public function unitOptions(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $rows = DB::table('vt_units')
            ->select('id', 'name')
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', '%'.$q.'%'))
            ->where('active', 1)
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn($r) => ['value' => $r->name, 'label' => $r->name]);

        return CustomResponse::success($rows);
    }
}
