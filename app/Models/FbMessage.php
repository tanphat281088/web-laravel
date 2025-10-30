<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbMessage extends Model
{
    protected $table = 'fb_messages';

    protected $fillable = [
        'conversation_id',
        'direction',        // 'in' | 'out'
        'mid',
        'text_raw',
        'text_translated',
        'text_polished',
        'src_lang',
        'dst_lang',
        'attachments',      // json
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
    ];

    // ===== Quan hệ =====
    public function conversation()
    {
        return $this->belongsTo(FbConversation::class, 'conversation_id');
    }

    // ===== Helpers =====
    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'out';
    }

    /**
     * Văn bản ưu tiên hiển thị ở UI (đã dịch nếu có)
     */
    public function preferredText(): ?string
    {
        return $this->text_translated
            ?? $this->text_polished
            ?? $this->text_raw;
    }
}
