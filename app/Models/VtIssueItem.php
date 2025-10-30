<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VtIssueItem extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'vt_issue_items';
    protected $guarded = [];
    protected $casts = [
        'so_luong' => 'integer',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(VtIssue::class, 'vt_issue_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VtItem::class, 'vt_item_id');
    }
}
