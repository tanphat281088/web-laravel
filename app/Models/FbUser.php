<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbUser extends Model
{
    protected $table = 'fb_users';

    protected $fillable = [
        'psid',
        'name',
        'locale',
        'timezone',
        'avatar',
        'first_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
    ];

    // Quan hệ
    public function conversations()
    {
        return $this->hasMany(FbConversation::class, 'fb_user_id');
    }
}
