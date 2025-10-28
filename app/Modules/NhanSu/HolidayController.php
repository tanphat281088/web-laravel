<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HolidayController extends BaseController
{
    // GET /nhan-su/holiday?from=&to=
    public function index(Request $request)
    {
        $v = Validator::make($request->all(), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $q = Holiday::query()->orderBy('ngay');
        if ($request->filled('from')) $q->where('ngay', '>=', $request->input('from'));
        if ($request->filled('to'))   $q->where('ngay', '<=', $request->input('to'));

        return $this->success(['items' => $q->get()]);
    }

    // POST /nhan-su/holiday  body: { ngay: YYYY-MM-DD, ten?, trang_thai? }
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'ngay'       => ['required', 'date_format:Y-m-d'],
            'ten'        => ['nullable', 'string', 'max:255'],
            'trang_thai' => ['nullable', 'boolean'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $row = Holiday::query()->updateOrCreate(
            ['ngay' => $request->input('ngay')],
            ['ten' => $request->input('ten'), 'trang_thai' => (bool) $request->input('trang_thai', true)]
        );
        return $this->success(['item' => $row], 'HOLIDAY_SAVED', 201);
    }

    // PATCH /nhan-su/holiday/{id}
    public function update(Request $request, int $id)
    {
        $v = Validator::make($request->all(), [
            'ten'        => ['nullable', 'string', 'max:255'],
            'trang_thai' => ['nullable', 'boolean'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $row = Holiday::query()->find($id);
        if (!$row) return $this->failed([], 'NOT_FOUND', 404);

        $row->update([
            'ten'        => $request->input('ten', $row->ten),
            'trang_thai' => $request->has('trang_thai') ? (bool) $request->input('trang_thai') : $row->trang_thai,
        ]);
        return $this->success(['item' => $row], 'HOLIDAY_UPDATED');
    }

    // DELETE /nhan-su/holiday/{id}
    public function destroy(int $id)
    {
        $row = Holiday::query()->find($id);
        if (!$row) return $this->failed([], 'NOT_FOUND', 404);
        $row->delete();
        return $this->success([], 'HOLIDAY_DELETED');
    }

    private function success($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class))
            return \App\Class\CustomResponse::success($data, $code, $status);
        return response()->json(['success' => true, 'code' => $code, 'data' => $data], $status);
    }
    private function failed($data = [], string $code = 'ERROR', int $status = 400)
    {
        if (class_exists(\App\Class\CustomResponse::class))
            return \App\Class\CustomResponse::failed($data, $code, $status);
        return response()->json(['success' => false, 'code' => $code, 'data' => $data], $status);
    }
}
