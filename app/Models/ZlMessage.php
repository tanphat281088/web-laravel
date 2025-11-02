<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZlMessage extends Model
{
    protected $table = 'zl_messages';

    protected $fillable = [
        'conversation_id',
        'direction',              // 'in' | 'out'
        'provider_message_id',    // ID phía Zalo (nếu có)
        'text_raw',
        'text_translated',
        'text_polished',
        'src_lang',
        'dst_lang',
        'attachments',            // json
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'attachments'  => 'array',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
    ];

    // Quan hệ
    public function conversation()
    {
        return $this->belongsTo(ZlConversation::class, 'conversation_id');
    }

    // Helpers
    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'out';
    }

    /** Văn bản ưu tiên hiển thị ở UI (đã dịch nếu có) */
    public function preferredText(): ?string
    {
        return $this->text_translated
            ?? $this->text_polished
            ?? $this->text_raw;
    }
}
