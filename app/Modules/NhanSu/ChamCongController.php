<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\ChamCong;
use App\Models\DiemLamViec;
use Throwable;

class ChamCongController extends BaseController
{
    /**
     * POST /nhan-su/cham-cong/checkin
     * Body JSON: { lat: number, lng: number, accuracy_m?: number, device_id?: string }
     */
    public function checkin(Request $request)
    {
        $user = $request->user();
        $userId = $user?->id ?? auth()->id();
        if (!$userId) {
            return $this->respond(false, 'UNAUTHORIZED', null, 401);
        }

        $v = Validator::make($request->all(), [
            'lat'        => ['required', 'numeric', 'between:-90,90'],
            'lng'        => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'integer', 'min:0'],
            'device_id'  => ['nullable', 'string', 'max:100'],
        ], [], [
            'lat' => 'lat',
            'lng' => 'lng',
        ]);

        if ($v->fails()) {
            return $this->respond(false, 'VALIDATION_ERROR', $v->errors(), 422);
        }

        $lat        = (float) $request->input('lat');
        $lng        = (float) $request->input('lng');
        $accuracy   = $request->input('accuracy_m');
        $deviceId   = $request->input('device_id');
        $clientIp   = $request->ip();
        $now        = now();
        $today      = $now->toDateString();

        $diem = DiemLamViec::nearest($lat, $lng);
        if (!$diem) {
            return $this->respond(false, 'NO_WORKPOINT', 'Chưa cấu hình điểm làm việc (geofence).', 503);
        }

        [$within, $distanceM] = $diem->withinGeofence($lat, $lng);
        if (!$within) {
            return $this->respond(false, 'OUT_OF_GEOFENCE', 'Chỉ cho phép chấm công tại công ty.', 403, [
                'distance_m' => $distanceM,
                'ban_kinh_m' => (int) $diem->ban_kinh_m,
            ]);
        }

        $exists = ChamCong::query()
            ->ofUser($userId)
            ->checkin()
            ->onDate($today)
            ->exists();

        if ($exists) {
            return $this->respond(false, 'ALREADY_CHECKED_IN', 'Bạn đã check-in hôm nay.', 409);
        }

        try {
            $log = null;
            DB::transaction(function () use (
                &$log, $userId, $lat, $lng, $accuracy, $distanceM, $deviceId, $clientIp, $now
            ) {
                $log = ChamCong::create([
                    'user_id'         => $userId,
                    'type'            => 'checkin',
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

            // Recompute ngay cho user theo kỳ 6→5
            $timesheetRecomputed = false;
            try {
                /** @var \App\Services\Timesheet\BangCongService $svc */
                $svc = app(\App\Services\Timesheet\BangCongService::class);
                $ym  = \App\Services\Timesheet\BangCongService::cycleLabelForDate($now);
                $svc->computeMonth($ym, (int) $userId);
                $timesheetRecomputed = true;

                \Log::info('CHECKIN recompute OK', ['uid' => $userId, 'ym' => $ym, 'at' => $now->toDateTimeString()]);
            } catch (\Throwable $e) {
                \Log::warning('CHECKIN recompute FAIL', [
                    'uid' => $userId, 'at' => $now->toDateTimeString(), 'err' => $e->getMessage()
                ]);
            }

            $respData = [
                'log' => [
                    'id'        => $log->id,
                    'desc'      => $log->shortDesc(),
                    'checked_at'=> $log->checked_at,
                    'distance_m'=> $log->distance_m,
                    'within'    => (bool)$log->within_geofence,
                ],
                'workpoint' => [
                    'id'         => $diem->id,
                    'ten'        => $diem->ten,
                    'ban_kinh_m' => (int) $diem->ban_kinh_m,
                ],
            ];

            if (config('app.debug')) {
                $respData['debug'] = [
                    'timesheet_recomputed' => $timesheetRecomputed,
                ];
            }

            return $this->respond(true, 'CHECKIN_OK', $respData);
        } catch (Throwable $e) {
            return $this->respond(false, 'SERVER_ERROR', config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 500);
        }
    }

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
            'data'    => $data,
            'extra'   => $extra,
        ], $status);
    }
}
