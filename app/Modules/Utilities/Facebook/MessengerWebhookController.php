<?php

namespace App\Modules\Utilities\Facebook;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MessengerWebhookController extends Controller
{
    // GET /api/fb/webhook  (verify)
    public function verify(Request $request)
    {
        // Placeholder: trả "OK" để bạn test nhanh NGINX/HTTPS
        return response('OK', 200);
    }

    // POST /api/fb/webhook  (receive)
    public function receive(Request $request)
    {
        // Placeholder: chưa xử lý payload
        return response()->json(['success' => true, 'message' => 'placeholder']);
    }
}
