<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\ChamCong;
use App\Models\DiemLamViec;
use Carbon\Carbon;
use Throwable;

class ChamCongCheckoutController extends BaseController
{
    /**
     * POST /nhan-su/cham-cong/checkout
     * Body JSON: { lat: number, lng: number, accuracy_m?: number, device_id?: string }
     * Header (khuyến nghị): X-Device-ID
     */
    public function checkout(Request $request)
    {
        $user = $request->user();
        $userId = $user?->id ?? auth()->id();
        if (!$userId) {
            return $this->respond(false, 'UNAUTHORIZED', null, 401);
        }

        // ===== Validate input =====
        $v = Validator::make($request->all(), [
            'lat'        => ['required', 'numeric', 'between:-90,90'],
            'lng'        => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'integer', 'min:0'],
            'device_id'  => ['nullable', 'string', 'max:100'],
        ]);
        if ($v->fails()) {
            return $this->respond(false, 'VALIDATION_ERROR', $v->errors(), 422);
        }

        $lat      = (float) $request->input('lat');
        $lng      = (float) $request->input('lng');
        $accuracy = $request->input('accuracy_m'); // có thể null
        $deviceId = $request->input('device_id') ?: $request->header('X-Device-ID') ?: 'WEB';
        $clientIp = $request->ip();

        // Dùng timezone app cho nhất quán
        $now   = Carbon::now(config('app.timezone'));
        $today = $now->toDateString();

        // ===== Tìm geofence gần nhất và kiểm tra =====
        $diem = DiemLamViec::nearest($lat, $lng);
        if (!$diem) {
            return $this->respond(false, 'NO_WORKPOINT', 'Chưa cấu hình điểm làm việc (geofence).', 503);
        }

        [$within, $distanceM] = $diem->withinGeofence($lat, $lng);
        if (!$within) {
            return $this->respond(false, 'OUT_OF_GEOFENCE', [
                'message'     => 'Chỉ cho phép chấm công tại công ty.',
                'distance_m'  => (int) $distanceM,
                'ban_kinh_m'  => (int) $diem->ban_kinh_m,
                'workpointId' => (int) $diem->id,
            ], 403);
        }

        // (tùy chọn) Nếu accuracy quá lớn, có thể chặn
        // if ($accuracy !== null && $accuracy > 100) {
        //     return $this->respond(false, 'LOW_ACCURACY', 'Sai số định vị quá lớn.', 422, ['accuracy_m' => (int)$accuracy]);
        // }

        // ===== Kiểm tra đã CHECK-IN hôm nay chưa =====
        $checkin = ChamCong::query()
            ->ofUser($userId)
            ->checkin()
            ->onDate($today)
            ->latest('checked_at')
            ->first();

        if (!$checkin) {
            return $this->respond(false, 'NO_CHECKIN_TODAY', 'Bạn chưa check-in hôm nay.', 409);
        }

        // ===== Chặn CHECK-OUT trùng trong ngày =====
        $exists = ChamCong::query()
            ->ofUser($userId)
            ->checkout()
            ->onDate($today)
            ->exists();

        if ($exists) {
            return $this->respond(false, 'ALREADY_CHECKED_OUT', 'Bạn đã check-out hôm nay.', 409);
        }

        // ===== Idempotency nhẹ: nếu có bản ghi checkout rất gần thời điểm hiện tại => coi như đã tạo =====
        $recent = ChamCong::query()
            ->ofUser($userId)
            ->checkout()
            ->where('checked_at', '>=', $now->copy()->subMinutes(2)) // 2 phút
            ->latest('checked_at')
            ->first();
        if ($recent) {
            return $this->respond(true, 'CHECKOUT_OK', [
                'log' => [
                    'id'         => $recent->id,
                    'desc'       => $recent->shortDesc(),
                    'checked_at' => $recent->checked_at,
                    'distance_m' => $recent->distance_m,
                    'within'     => (bool) $recent->within_geofence,
                ],
                'workpoint' => [
                    'id'         => (int) $diem->id,
                    'ten'        => $diem->ten,
                    'ban_kinh_m' => (int) $diem->ban_kinh_m,
                ],
            ], 200);
        }

        try {
            $log = null;
            DB::transaction(function () use (
                &$log, $userId, $lat, $lng, $accuracy, $distanceM, $deviceId, $clientIp, $now
            ) {
                $log = ChamCong::create([
                    'user_id'         => $userId,
                    'type'            => 'checkout',
                    'lat'             => $lat,
                    'lng'             => $lng,
                    'accuracy_m'      => $accuracy,
                    'distance_m'      => $distanceM,
                    'within_geofence' => 1,
                    'device_id'       => $deviceId,
                    'ip'              => $clientIp,
                    'checked_at'      => $now,
                    'ghi_chu'         => null,
                ]);
            });

            // (tùy chọn) có thể tính nhanh thời gian làm việc = checkout - checkin gần nhất
            // $workedMinutes = $checkin ? $checkin->checked_at->diffInMinutes($now) : null;

            return $this->respond(true, 'CHECKOUT_OK', [
                'log' => [
                    'id'         => $log->id,
                    'desc'       => $log->shortDesc(),
                    'checked_at' => $log->checked_at,
                    'distance_m' => $log->distance_m,
                    'within'     => (bool) $log->within_geofence,
                ],
                'workpoint' => [
                    'id'         => (int) $diem->id,
                    'ten'        => $diem->ten,
                    'ban_kinh_m' => (int) $diem->ban_kinh_m,
                ],
            ], 201);
        } catch (Throwable $e) {
            return $this->respond(false, 'SERVER_ERROR', config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 500);
        }
    }

    /**
     * Chuẩn hóa response (tương thích CustomResponse nếu có).
     * $extra dùng để trả kèm meta khi cần.
     */
    private function respond(bool $success, string $code, $data = null, int $status = 200, array $extra = [])
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            if ($success) {
                return \App\Class\CustomResponse::success($data ?? $extra, $code)->setStatusCode($status);
            }
            return \App\Class\CustomResponse::failed($data ?? $extra, $code)->setStatusCode($status);
        }

        return response()->json([
            'success' => $success,
            'code'    => $code,
            'data'    => $data ?? $extra,
        ], $status);
    }
}
