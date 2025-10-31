<?php

namespace App\Console\Commands;

use App\Models\FbConversation;
use App\Models\FbPage;
use App\Models\FbUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class FbBackfillParticipants extends Command
{
    protected $signature = 'fb:backfill-participants
                            {--page-id= : Facebook PAGE_ID (mặc định: lấy từ 1 conversation gần nhất)}
                            {--limit=1000 : Số user thiếu tên cần lấp (tối đa mỗi lượt)}';

    protected $description = 'Backfill fb_users.name/avatar từ /{PAGE_ID}/conversations (participants, có phân trang).';

    public function handle(): int
    {
        $this->info('== FB Backfill participants (name/avatar) ==');

        // 1) Xác định PAGE_ID + PAGE TOKEN
        $pageIdOpt = (string)($this->option('page-id') ?? '');
        if ($pageIdOpt !== '') {
            $page = FbPage::query()->where('page_id', $pageIdOpt)->first();
        } else {
            $anyConv = FbConversation::query()->latest('id')->first();
            $page = $anyConv ? FbPage::query()->find($anyConv->page_id) : null;
        }
        if (!$page) { $this->error('Không tìm thấy page. Thử --page-id=PAGE_ID'); return self::FAILURE; }
        if (empty($page->token_enc)) { $this->error('Page chưa có token_enc (PAT).'); return self::FAILURE; }

        try { $token = Crypt::decryptString($page->token_enc); }
        catch (\Throwable $e) { $this->error('Giải mã token_enc lỗi: '.$e->getMessage()); return self::FAILURE; }

        $this->line("Page ID: {$page->page_id}");

        // 2) Tải participants qua paging
        $map = $this->fetchParticipantsPaged($page->page_id, $token);
        $this->info('Participants loaded: '.count($map));
        if (empty($map)) $this->warn('Không lấy được participants nào.');

        // 3) Lấp tên/ảnh cho fb_users đang trống
        $limit = (int)$this->option('limit');
        $users = FbUser::query()->where(function ($q) {
            $q->whereNull('name')->orWhere('name','');
        })->limit($limit)->get();

        $this->line('Users missing name: '.$users->count());

        $filled = 0;
        foreach ($users as $u) {
            $psid = (string)$u->psid;
            if (!isset($map[$psid])) continue;

            $info  = $map[$psid];
            $dirty = false;
            if (!empty($info['name']) && empty($u->name))   { $u->name   = $info['name']; $dirty = true; }
            if (!empty($info['avatar']) && empty($u->avatar)) { $u->avatar = $info['avatar']; $dirty = true; }
            if ($dirty) { $u->save(); $filled++; }
        }

        $this->info("Filled users: {$filled}");
        $this->info('Done.');
        return self::SUCCESS;
    }

    /** Đọc toàn bộ participants qua paging */
    private function fetchParticipantsPaged(string $pageId, string $token): array
    {
        $all = [];
        $url = "https://graph.facebook.com/v19.0/{$pageId}/conversations";
        $params = [
            'fields'       => 'participants{id,name,picture}',
            'limit'        => 100,
            'access_token' => $token,
        ];
        do {
            $resp = Http::get($url, $params);
            if (!$resp->ok()) { $this->warn('Fail: status='.$resp->status().' body='.$resp->body()); break; }
            $j = $resp->json();
            foreach (($j['data'] ?? []) as $row) {
                foreach (($row['participants']['data'] ?? []) as $p) {
                    $id = (string)($p['id'] ?? '');
                    if ($id === '') continue;
                    $all[$id] = [
                        'name'   => $p['name'] ?? null,
                        'avatar' => $p['picture']['data']['url'] ?? null,
                    ];
                }
            }
            $url    = $j['paging']['next'] ?? null; // next chứa full query → không cần $params
            $params = [];
        } while (!empty($url));

        return $all;
    }
}
