<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FbPage extends Model
{
    use SoftDeletes;

    protected $table = 'fb_pages';

    protected $fillable = [
        'page_id',
        'name',
        'token_enc',
        'status',
        'last_health_check_at',
        'settings',
    ];

    protected $casts = [
        'last_health_check_at' => 'datetime',
        'settings'             => 'array',
    ];

    // Quan hệ
    public function conversations()
    {
        return $this->hasMany(FbConversation::class, 'page_id');
    }
}
