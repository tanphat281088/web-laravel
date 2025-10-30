<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbGlossary extends Model
{
    protected $table = 'fb_glossaries';

    protected $fillable = [
        'term',
        'prefer_keep',
        'prefer_translation',
        'note',
    ];

    protected $casts = [
        'prefer_keep' => 'boolean',
    ];
}
