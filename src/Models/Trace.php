<?php

namespace RyderAsKing\LaravelAiTrace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trace extends Model
{
    protected $table = 'ai_traces';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function spans(): HasMany
    {
        return $this->hasMany(Span::class);
    }
}
