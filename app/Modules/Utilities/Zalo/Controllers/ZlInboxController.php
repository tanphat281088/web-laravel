<?php
namespace App\Modules\Utilities\Zalo\Controllers;


use App\Models\ZlConversation;
use App\Models\ZlMessage;
use App\Models\ZlUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use App\Jobs\Zl\SendZlReplyJob;
use App\Modules\Utilities\Zalo\Services\ZaloOAuthService;


/**
 * Inbox Zalo — khung an toàn (JSON chạy được ngay).
 * Chưa gọi API Zalo thật; FE có thể render list/thread + health để kiểm tra route.
 */
class ZlInboxController extends Controller
{
    /**
     * GET /api/utilities/zl/health
     */
public function health()
{
    $enabled  = filter_var(env('ZL_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    $provider = env('ZL_TRANSLATE_PROVIDER', env('FB_TRANSLATE_PROVIDER', 'google_apikey'));
    $aiPolish = filter_var(env('ZL_AI_POLISH', env('FB_AI_POLISH', false)), FILTER_VALIDATE_BOOLEAN);
    $aiTone   = env('ZL_AI_TONE', env('FB_AI_TONE', 'neutral'));

    // Đọc TTL nếu có, không ném lỗi
    $ttl       = (new ZaloOAuthService())->ttl();
    $accessTtl = $ttl['access'] ?? null;
    $refreshTtl= $ttl['refresh'] ?? null;

    return response()->json([
        'enabled'               => $enabled,
        'provider'              => $provider,
        'ai_polish'             => $aiPolish,
        'ai_tone'               => $aiTone,
        'access_token_ttl_sec'  => $accessTtl,   // có thể null
        'refresh_token_ttl_sec' => $refreshTtl,  // có thể null
        'need_reauth'           => ($refreshTtl !== null && $refreshTtl <= 24*3600),
    ]);
}


    /**
     * GET /api/utilities/zl/conversations
     * MVP: trả danh sách rỗng nếu chưa có dữ liệu.
     */
    public function conversations(Request $request)
    {
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = max(1, min(100, (int) $request->query('per_page', 20)));
        $q        = trim((string) $request->query('q', ''));
        $status   = $request->query('status');            // 'open' | 'closed' (tuỳ chọn)
        $assigned = $request->query('assigned');          // 'mine' | 'unassigned' (tuỳ chọn)
        $meId     = optional(auth()->user())->id;

        $query = ZlConversation::query()
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
            $query->where(function ($qq) use ($q) {
                $qq->whereHas('user', function ($u) use ($q) {
                    $u->where('name', 'like', "%{$q}%");
                })->orWhere('id', (int) $q);
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (ZlConversation $c) {
            $latest = $c->latestMessage;
            $canSend = $c->can_send_until_at
                ? Carbon::parse($c->can_send_until_at)->isFuture()
                : true;

            return [
                'id'                => $c->id,
                'assigned_user_id'  => $c->assigned_user_id,
                'status'            => (int) $c->status === 1 ? 'open' : 'closed',
                'lang_primary'      => $c->lang_primary,
                'can_send_until_at' => optional($c->can_send_until_at)->toDateTimeString(),
                'within24h'         => $canSend, // FE tái dùng prop cũ
                'tags'              => $c->tags ?: [],
                'last_message_at'   => optional($c->last_message_at)->toDateTimeString(),
                // tiện FE
                'customer_name'     => optional($c->user)->name ?: ('Khách ' . substr((string)optional($c->user)->zalo_user_id, -4)),
                'latest_message_vi' => $latest ? ($latest->text_translated ?? $latest->text_polished ?? $latest->text_raw) : null,
                'latest_message_at' => $latest ? optional($latest->created_at)->toDateTimeString() : null,
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
     * GET /api/utilities/zl/conversations/{id}
     */
    public function show(int $id)
    {
        $conv = ZlConversation::query()->find($id);
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $msgs = ZlMessage::query()
            ->where('conversation_id', $conv->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (ZlMessage $m) {
                return [
                    'id'              => $m->id,
                    'conversation_id' => $m->conversation_id,
                    'direction'       => $m->direction,
                    'mid'             => $m->provider_message_id, // FE đang dùng 'mid' → map tạm
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
     * POST /api/utilities/zl/conversations/{id}/reply
     * Body: { text_vi: string, polish?: bool, tone?: string }
     * Placeholder: lưu outbound thô; gửi OA thật sẽ bổ sung qua Job ở bước tiếp theo.
     */
    public function reply(int $id, Request $request)
    {
        $conv = ZlConversation::query()->find($id);
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $textVi = trim((string) $request->input('text_vi', ''));
        if ($textVi === '') {
            return response()->json(['success' => false, 'message' => 'text_vi is required'], 422);
        }

        // Áp quy tắc can_send (thay within24h)
        $canSend = $conv->can_send_until_at ? Carbon::parse($conv->can_send_until_at)->isFuture() : true;
        if (!$canSend) {
            return response()->json(['success' => false, 'message' => 'Sending temporarily blocked by policy/quota'], 400);
        }

        // Lưu message OUT thô (chưa gửi OA)
        $msg = new ZlMessage();
        $msg->conversation_id = $conv->id;
        $msg->direction       = 'out';
        $msg->text_raw        = $textVi;
        $msg->src_lang        = 'vi';
        $msg->dst_lang        = null;
        $msg->attachments     = null;
        $msg->save();

        $conv->last_message_at = now();
        $conv->save();

dispatch(new SendZlReplyJob($conv->id, $msg->id));


        return response()->json([
            'success'         => true,
            'conversation_id' => $conv->id,
            'sent'            => true,   // queued (placeholder)
            'text_vi'         => $textVi,
            'note'            => 'queued to send via SendZlReplyJob (placeholder)',
        ]);
    }

    /**
     * POST /api/utilities/zl/conversations/{id}/assign
     */
    public function assign(int $id, Request $request)
    {
        $conv = ZlConversation::query()->find($id);
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
     * PATCH /api/utilities/zl/conversations/{id}/status
     */
    public function status(int $id, Request $request)
    {
        $conv = ZlConversation::query()->find($id);
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
