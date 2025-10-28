<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignJob extends Model
{
    protected $table = 'sign_jobs';

    protected $fillable = [
        'user_id', 'input_text', 'template_codes', 'export_type',
        'options', 'status', 'result_paths', 'started_at', 'finished_at',
        'error_message',
    ];

    protected $casts = [
        'template_codes' => 'array',
        'options'        => 'array',
        'result_paths'   => 'array',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';
}
