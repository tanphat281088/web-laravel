<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VtIssue extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'vt_issues';
    protected $guarded = [];
    protected $casts = [
        'ngay_ct'       => 'date',
        'tong_gia_tri'  => 'decimal:2',
        'tong_so_luong' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(VtIssueItem::class, 'vt_issue_id');
    }
}
