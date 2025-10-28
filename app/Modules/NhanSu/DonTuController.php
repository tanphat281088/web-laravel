<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\DonTuNghiPhep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Throwable;

class DonTuController extends BaseController
{
    /**
     * POST /nhan-su/don-tu
     * Tạo đơn từ cho chính user đăng nhập.
     */
    public function store(Request $request)
    {
        $userId = $request->user()?->id ?? auth()->id();
        if (!$userId) {
            return $this->failed([], 'UNAUTHORIZED', 401);
        }

        // Chấp nhận cả ISO; parse lại bằng Carbon
        $v = Validator::make($request->all(), [
            'loai'        => ['required','string','max:50', Rule::in([
                'nghi_phep','khong_luong','di_tre','ve_som','lam_viec_tu_xa','khac'
            ])],
            'tu_ngay'     => ['nullable','date'],
            'den_ngay'    => ['nullable','date','after_or_equal:tu_ngay'],
            'so_gio'      => ['nullable','integer','min:1','max:168'],
            'ly_do'       => ['nullable','string','max:5000'],     // lý do NV
            'attachments' => ['nullable','array'],
        ]);
        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $tu  = $request->filled('tu_ngay')  ? Carbon::parse($request->input('tu_ngay'))->toDateString()  : null;
        $den = $request->filled('den_ngay') ? Carbon::parse($request->input('den_ngay'))->toDateString() : null;

        // Ràng buộc: theo NGÀY hoặc theo GIỜ
        $soGio = $request->input('so_gio');
        if (($tu || $den) && $soGio) {
            return $this->failed(['message' => 'Chọn theo ngày HOẶC theo giờ, không đồng thời.'], 'VALIDATION_ERROR', 422);
        }
        if (!($tu || $den) && !$soGio) {
            return $this->failed(['message' => 'Vui lòng chọn khoảng ngày HOẶC nhập số giờ.'], 'VALIDATION_ERROR', 422);
        }

        try {
            $row = null;
            DB::transaction(function () use (&$row, $request, $userId, $tu, $den, $soGio) {
                $row = DonTuNghiPhep::create([
                    'user_id'       => $userId,
                    'loai'          => $request->input('loai'),
                    'tu_ngay'       => $tu,
                    'den_ngay'      => $den,
                    'so_gio'        => ($tu || $den) ? null : $soGio, // có ngày thì không lưu giờ
                    'ly_do'         => $request->input('ly_do'),       // lý do NV
                    'ly_do_tu_choi' => null,                           // mặc định chưa có lý do QL
                    'attachments'   => $request->input('attachments', []),
                    'trang_thai'    => DonTuNghiPhep::TRANG_THAI_PENDING,
                ]);
            });

            return $this->success([
                'item'   => $this->toApi($row),
                'notice' => 'Đã gửi đơn và chờ duyệt.',
            ], 'LEAVE_CREATED', 201);
        } catch (Throwable $e) {
            return $this->failed(config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 'SERVER_ERROR', 500);
        }
    }

    /**
     * GET /nhan-su/don-tu/my
     * Danh sách đơn của chính user.
     */
    public function myIndex(Request $request)
    {
        $userId = $request->user()?->id ?? auth()->id();
        if (!$userId) {
            return $this->failed([], 'UNAUTHORIZED', 401);
        }

        $v = Validator::make($request->all(), [
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d'],
            'type'     => ['nullable', 'string', 'max:50'],
            'status'   => ['nullable', 'integer', 'in:0,1,2,3'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $from    = $request->input('from');
        $to      = $request->input('to');
        $type    = $request->input('type');
        $status  = $request->input('status');
        $perPage = (int) $request->input('per_page', 20);
        $page    = (int) $request->input('page', 1);

        $q = DonTuNghiPhep::query()->ofUser($userId);
        if ($from || $to) $q->betweenDates($from, $to);
        if ($type)        $q->type($type);
        if ($status !== null && $status !== '') $q->status((int) $status);
        $q->orderByDesc('created_at');

        $p = $q->paginate($perPage, ['*'], 'page', $page);
        $items = collect($p->items())->map(fn (DonTuNghiPhep $r) => $this->toApi($r));

        return $this->success([
            'filter' => compact('from','to','type','status'),
            'pagination' => [
                'total'        => $p->total(),
                'per_page'     => $p->perPage(),
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'has_more'     => $p->hasMorePages(),
            ],
            'items' => $items,
        ], 'MY_LEAVES');
    }

    /**
     * GET /nhan-su/don-tu
     * Danh sách đơn (cho quản lý/HR).
     */
    public function adminIndex(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id'  => ['nullable', 'integer', 'min:1'],
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d'],
            'type'     => ['nullable', 'string', 'max:50'],
            'status'   => ['nullable', 'integer', 'in:0,1,2,3'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $userId  = $request->input('user_id');
        $from    = $request->input('from');
        $to      = $request->input('to');
        $type    = $request->input('type');
        $status  = $request->input('status');
        $perPage = (int) $request->input('per_page', 20);
        $page    = (int) $request->input('page', 1);

        try {
            $q = DonTuNghiPhep::query();
            if ($userId)      $q->ofUser((int) $userId);
            if ($from || $to) $q->betweenDates($from, $to);
            if ($type)        $q->type($type);
            if ($status !== null && $status !== '') $q->status((int) $status);

            // Chỉ lấy các cột chắc chắn tồn tại trên users (name/email)
            $q->with([
                'user:id,name,email',
                'approver:id,name,email',
            ])->orderByDesc('created_at');

            $p = $q->paginate($perPage, ['*'], 'page', $page);

            $items = collect($p->items())->map(function (DonTuNghiPhep $r) {
                $o = $this->toApi($r);
                $o['user_name']     = $r->relationLoaded('user') && $r->user ? ($r->user->name ?? $r->user->email) : null;
                $o['approver_name'] = $r->relationLoaded('approver') && $r->approver ? ($r->approver->name ?? $r->approver->email) : null;
                return $o;
            });

            return $this->success([
                'filter' => compact('userId','from','to','type','status'),
                'pagination' => [
                    'total'        => $p->total(),
                    'per_page'     => $p->perPage(),
                    'current_page' => $p->currentPage(),
                    'last_page'    => $p->lastPage(),
                    'has_more'     => $p->hasMorePages(),
                ],
                'items' => $items,
            ], 'ADMIN_LEAVES');
        } catch (\Throwable $e) {
            return $this->failed(config('app.debug') ? $e->getMessage() : [], 'SERVER_ERROR', 500);
        }
    }

    /**
     * PATCH /nhan-su/don-tu/{id}/approve
     */
    public function approve(Request $request, int $id)
    {
        return $this->changeStatus($request, $id, DonTuNghiPhep::TRANG_THAI_APPROVED, 'APPROVED_OK');
    }

    /**
     * PATCH /nhan-su/don-tu/{id}/reject
     */
    public function reject(Request $request, int $id)
    {
        return $this->changeStatus($request, $id, DonTuNghiPhep::TRANG_THAI_REJECTED, 'REJECTED_OK');
    }

    /**
     * PATCH /nhan-su/don-tu/{id}/cancel
     */
    public function cancel(Request $request, int $id)
    {
        $userId = $request->user()?->id ?? auth()->id();
        if (!$userId) {
            return $this->failed([], 'UNAUTHORIZED', 401);
        }

        /** @var DonTuNghiPhep|null $row */
        $row = DonTuNghiPhep::query()->where('id', $id)->where('user_id', $userId)->first();
        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }
        if (!$row->isPending()) {
            return $this->failed([], 'ONLY_PENDING_CAN_CANCEL', 409);
        }

        try {
            DB::transaction(function () use ($row) {
                $row->update([
                    'trang_thai'    => DonTuNghiPhep::TRANG_THAI_CANCELED,
                    'approver_id'   => null,
                    'approved_at'   => null,
                    'ly_do_tu_choi' => null, // reset luôn để không “đọng” lý do cũ
                ]);
            });
            return $this->success(['item' => $this->toApi($row->fresh())], 'CANCELED_OK');
        } catch (Throwable $e) {
            return $this->failed(config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 'SERVER_ERROR', 500);
        }
    }

    // ===== Helpers =====

    private function changeStatus(Request $request, int $id, int $targetStatus, string $okCode)
    {
        $approverId = $request->user()?->id ?? auth()->id();
        if (!$approverId) {
            return $this->failed([], 'UNAUTHORIZED', 401);
        }

        /** @var DonTuNghiPhep|null $row */
        $row = DonTuNghiPhep::query()->find($id);
        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }
        if (!$row->isPending()) {
            return $this->failed([], 'ONLY_PENDING_CAN_UPDATE', 409);
        }

        try {
            DB::transaction(function () use ($row, $targetStatus, $approverId, $request) {
                $payload = [
                    'trang_thai'  => $targetStatus,
                    'approver_id' => $approverId,
                    'approved_at' => now(),
                ];

                if ($targetStatus === DonTuNghiPhep::TRANG_THAI_REJECTED) {
                    // Ưu tiên ly_do_tu_choi; fallback ly_do/ghi_chu để tương thích FE
                    $reason = trim(
                        $request->input('ly_do_tu_choi')
                        ?? $request->input('ly_do')
                        ?? $request->input('ghi_chu')
                        ?? ''
                    ) ?: null;
                    $payload['ly_do_tu_choi'] = $reason; // <- LÝ DO QL
                } else {
                    // APPROVE: reset lý do từ chối nếu trước đó có
                    $payload['ly_do_tu_choi'] = null;
                }

                $row->update($payload);
            });

            return $this->success(['item' => $this->toApi($row->fresh())], $okCode);
        } catch (Throwable $e) {
            return $this->failed(config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 'SERVER_ERROR', 500);
        }
    }

    private function toApi(DonTuNghiPhep $r): array
    {
        return [
            'id'               => $r->id,
            'user_id'          => $r->user_id,
            'loai'             => $r->loai,
            'loai_label'       => $r->typeLabel(),
            'tu_ngay'          => $r->tu_ngay?->toDateString(),
            'den_ngay'         => $r->den_ngay?->toDateString(),
            'so_gio'           => $r->so_gio,
            'ly_do'            => $r->ly_do,             // lý do NV
            'ly_do_tu_choi'    => $r->ly_do_tu_choi,     // lý do QL khi từ chối
            'trang_thai'       => $r->trang_thai,
            'trang_thai_label' => $r->statusLabel(),
            'approver_id'      => $r->approver_id,
            'approved_at'      => $r->approved_at?->toDateTimeString(),
            'attachments'      => $r->attachments,
            'short_desc'       => $r->shortDesc(),
            'created_at'       => $r->created_at?->toDateTimeString(),
        ];
    }

    // --- Response helpers ---
    private function success($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code, $status);
        }
        return response()->json(['success' => true, 'code' => $code, 'data' => $data], $status);
    }

    private function failed($data = [], string $code = 'ERROR', int $status = 400)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::failed($data, $code, $status);
        }
        return response()->json(['success' => false, 'code' => $code, 'data' => $data], $status);
    }
}
