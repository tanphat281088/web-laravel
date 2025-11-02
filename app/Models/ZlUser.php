<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZlUser extends Model
{
    protected $table = 'zl_users';

    protected $fillable = [
        'zalo_user_id',
        'name',
        'avatar_url',
        'locale',
        'is_sensitive',
        'first_seen_at',
    ];

    protected $casts = [
        'is_sensitive'  => 'boolean',
        'first_seen_at' => 'datetime',
    ];

    public function conversations()
    {
        return $this->hasMany(ZlConversation::class, 'zl_user_id');
    }
}
