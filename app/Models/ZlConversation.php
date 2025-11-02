<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZlConversation extends Model
{
    use SoftDeletes;

    protected $table = 'zl_conversations';

    protected $fillable = [
        'zl_user_id',
        'assigned_user_id',
        'status',
        'lang_primary',
        'can_send_until_at',
        'tags',
        'last_message_at',
    ];

    protected $casts = [
        'can_send_until_at' => 'datetime',
        'last_message_at'   => 'datetime',
        'tags'              => 'array',
    ];

    // Quan hệ
    public function user()
    {
        return $this->belongsTo(ZlUser::class, 'zl_user_id');
    }

    public function messages()
    {
        return $this->hasMany(ZlMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(ZlMessage::class, 'conversation_id')->latest('created_at');
    }

    // Scopes
    public function scopeOpen($q)    { return $q->where('status', 1); }
    public function scopeClosed($q)  { return $q->where('status', 0); }

    public function scopeCanSend($q)
    {
        return $q->where(function ($qq) {
            $qq->whereNull('can_send_until_at')
               ->orWhere('can_send_until_at', '>', now());
        });
    }
}
