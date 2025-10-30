<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FbConversation extends Model
{
    use SoftDeletes;

    protected $table = 'fb_conversations';

    protected $fillable = [
        'page_id',
        'fb_user_id',
        'assigned_user_id',
        'status',
        'lang_primary',
        'within_24h_until_at',
        'tags',
        'last_message_at',
    ];

    protected $casts = [
        'within_24h_until_at' => 'datetime',
        'last_message_at'     => 'datetime',
        'tags'                => 'array',
    ];

    // ===== Quan hệ =====
    public function page()
    {
        return $this->belongsTo(FbPage::class, 'page_id');
    }

    public function user()
    {
        return $this->belongsTo(FbUser::class, 'fb_user_id');
    }

    public function assignedUser()
    {
        // users table sẵn có trong hệ thống của bạn
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages()
    {
        return $this->hasMany(FbMessage::class, 'conversation_id');
    }

    // Tin nhắn mới nhất (tiện dùng list)
    public function latestMessage()
    {
        return $this->hasOne(FbMessage::class, 'conversation_id')->latest('created_at');
    }

    // ===== Scopes tiện dụng =====
    public function scopeOpen($query)
    {
        return $query->where('status', 1);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 0);
    }

    public function scopeWithin24h($query)
    {
        return $query->whereNotNull('within_24h_until_at')
                     ->where('within_24h_until_at', '>', now());
    }
}
