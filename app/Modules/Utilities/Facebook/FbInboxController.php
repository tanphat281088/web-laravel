<?php

namespace App\Modules\Utilities\Facebook;

use App\Models\FbConversation;
use App\Models\FbMessage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class FbInboxController extends Controller
{
    /**
     * GET /api/utilities/fb/health
     * Đọc flags .env để FE biết có bật module không (placeholder, an toàn)
     */
    public function health()
    {
        $enabled   = filter_var(env('FB_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $provider  = env('TRANSLATE_PROVIDER', 'google');
        $aiPolish  = filter_var(env('AI_POLISH_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $aiTone    = env('AI_TONE', 'neutral');

        return response()->json([
            'enabled'   => $enabled,
            'provider'  => $provider,
            'ai_polish' => $aiPolish,
            'ai_tone'   => $aiTone,
        ]);
    }

    /**
     * GET /api/utilities/fb/conversations
     * Trả danh sách hội thoại (có phân trang, filter nhẹ).
     * Nếu DB chưa có dữ liệu → trả rỗng (FE vẫn chạy).
     */
    public function conversations(Request $request)
    {
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = max(1, min(100, (int) $request->query('per_page', 20)));
        $q        = trim((string) $request->query('q', ''));
        $status   = $request->query('status');            // 'open' | 'closed' (tuỳ chọn)
        $assigned = $request->query('assigned');          // 'mine' | 'unassigned' (tuỳ chọn)
        $meId     = optional(auth()->user())->id;

        $query = FbConversation::query()
            ->with(['user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($status === 'open')   { $query->where('status', 1); }
        if ($status === 'closed') { $query->where('status', 0); }

        if ($assigned === 'mine' && $meId) {
            $query->where('assigned_user_id', $meId);
        } elseif ($assigned === 'unassigned') {
            $query->whereNull('assigned_user_id');
        }

        if ($q !== '') {
            // tìm theo tên khách (nếu có) hoặc id
            $query->where(function ($qq) use ($q) {
                $qq->whereHas('user', function ($u) use ($q) {
                    $u->where('name', 'like', "%{$q}%");
                })->orWhere('id', (int) $q);
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (FbConversation $c) {
            $latest = $c->latestMessage;
            $within24h = $c->within_24h_until_at
                ? Carbon::parse($c->within_24h_until_at)->isFuture()
                : true;

            return [
                'id'                 => $c->id,
                'page_id'            => $c->page_id,
                'fb_user_id'         => $c->fb_user_id,
                'assigned_user_id'   => $c->assigned_user_id,
                'status'             => (int) $c->status === 1 ? 'open' : 'closed',
                'lang_primary'       => $c->lang_primary,
                'within_24h_until_at'=> optional($c->within_24h_until_at)->toDateTimeString(),
                'within24h'          => $within24h,
                'tags'               => $c->tags ?: [],
                'last_message_at'    => optional($c->last_message_at)->toDateTimeString(),
                // Các trường tiện FE
                'customer_name'      => optional($c->user)->name,
                'latest_message_vi'  => $latest ? ($latest->text_translated ?? $latest->text_polished ?? $latest->text_raw) : null,
                'latest_message_at'  => $latest ? optional($latest->created_at)->toDateTimeString() : null,
            ];
        })->values();

        return response()->json([
            'success'    => true,
            'data'       => $data,
            'pagination' => [
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total'    => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/utilities/fb/conversations/{id}
     * Trả thread messages của 1 hội thoại (chưa phân trang—MVP).
     */
    public function show(int $id)
    {
        $conv = FbConversation::query()->find($id);
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $msgs = FbMessage::query()
            ->where('conversation_id', $conv->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (FbMessage $m) {
                return [
                    'id'              => $m->id,
                    'conversation_id' => $m->conversation_id,
                    'direction'       => $m->direction,
                    'mid'             => $m->mid,
                    'text_raw'        => $m->text_raw,
                    'text_translated' => $m->text_translated,
                    'text_polished'   => $m->text_polished,
                    'src_lang'        => $m->src_lang,
                    'dst_lang'        => $m->dst_lang,
                    'attachments'     => $m->attachments ?: [],
                    'delivered_at'    => optional($m->delivered_at)->toDateTimeString(),
                    'read_at'         => optional($m->read_at)->toDateTimeString(),
                    'created_at'      => optional($m->created_at)->toDateTimeString(),
                ];
            })->values();

        return response()->json([
            'success'         => true,
            'conversation_id' => $conv->id,
            'messages'        => $msgs,
        ]);
    }

    /**
     * POST /api/utilities/fb/conversations/{id}/reply
     * MVP: chỉ lưu 1 message OUTBOUND vào DB, chưa gửi Facebook.
     * Body: { text_vi: string }
     */
    public function reply(int $id, Request $request)
    {
        $conv = FbConversation::query()->find($id);
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $textVi = trim((string) $request->input('text_vi', ''));
        if ($textVi === '') {
            return response()->json(['success' => false, 'message' => 'text_vi is required'], 422);
        }

        // Kiểm tra 24h window (nếu có set)
        $within24h = $conv->within_24h_until_at
            ? Carbon::parse($conv->within_24h_until_at)->isFuture()
            : true;
        if (!$within24h) {
            return response()->json(['success' => false, 'message' => 'Outside 24h window'], 400);
        }

        // Lưu outbound message (gốc: VI). Chưa dịch/gửi.
        $msg = new FbMessage();
        $msg->conversation_id = $conv->id;
        $msg->direction       = 'out';
        $msg->text_raw        = $textVi;     // gốc (VI)
        $msg->src_lang        = 'vi';
        $msg->dst_lang        = null;
        $msg->attachments     = null;
        $msg->save();

        // Cập nhật last_message_at
        $conv->last_message_at = now();
        $conv->save();

        return response()->json([
            'success'         => true,
            'conversation_id' => $conv->id,
            'sent'            => false, // chưa gửi FB
            'text_vi'         => $textVi,
            'note'            => 'saved to DB (placeholder, not sent)',
        ]);
    }

    /**
     * POST /api/utilities/fb/conversations/{id}/assign
     * MVP: chỉ set assigned_user_id = id được gửi lên.
     */
    public function assign(int $id, Request $request)
    {
        $conv = FbConversation::query()->find($id);
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $assigneeId = (int) $request->input('assigned_user_id', 0);
        if ($assigneeId <= 0) {
            return response()->json(['success' => false, 'message' => 'assigned_user_id is required'], 422);
        }

        $conv->assigned_user_id = $assigneeId;
        $conv->save();

        return response()->json([
            'success'          => true,
            'conversation_id'  => $conv->id,
            'assigned_user_id' => $assigneeId,
            'note'             => 'assigned (placeholder)',
        ]);
    }

    /**
     * PATCH /api/utilities/fb/conversations/{id}/status
     * MVP: đổi open/closed.
     */
    public function status(int $id, Request $request)
    {
        $conv = FbConversation::query()->find($id);
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $status = (string) $request->input('status', 'open'); // 'open' | 'closed'
        $conv->status = $status === 'closed' ? 0 : 1;
        $conv->save();

        return response()->json([
            'success'         => true,
            'conversation_id' => $conv->id,
            'status'          => $status,
            'note'            => 'status updated (placeholder)',
        ]);
    }
}
