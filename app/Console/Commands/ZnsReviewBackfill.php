<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ZnsReviewBackfill extends Command
{
    protected $signature = 'zns:review:backfill {--from=} {--to=} {--limit=500}';
    protected $description = 'Backfill ZNS Review invites for delivered/paid orders in a date range';

    public function handle(): int
    {
        $from  = $this->option('from');  // YYYY-MM-DD
        $to    = $this->option('to');    // YYYY-MM-DD
        $limit = (int) $this->option('limit');

        /** @var \App\Services\Zns\ZnsReviewService $svc */
        $svc = app(\App\Services\Zns\ZnsReviewService::class);
        $stat = $svc->backfillInvites($from, $to, $limit);

        $this->info("scanned={$stat['scanned']} created={$stat['created']} skipped={$stat['skipped']}");
        return self::SUCCESS;
    }
}
