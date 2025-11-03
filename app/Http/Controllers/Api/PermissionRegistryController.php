<?php

namespace App\Http\Controllers\Api;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;

class PermissionRegistryController extends Controller
{
    public function index()
    {
        // Bật/tắt bằng flag .env: PERMISSION_ENGINE=v2
        $engine = env('PERMISSION_ENGINE', 'permission'); // 'permission' (v1) | 'v2'

        if ($engine === 'v2') {
            $data = config('permission_registry');
            $version = 'v2';
        } else {
            $data = config('permission');
            $version = 'v1';
        }

        return CustomResponse::success([
            'version' => $version,
            'items'   => $data,
        ]);
    }
}
