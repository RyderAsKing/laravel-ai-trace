<?php

namespace RyderAsKing\LaravelAiTrace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Span extends Model
{
    protected $table = 'ai_spans';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function trace(): BelongsTo
    {
        return $this->belongsTo(Trace::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SpanEvent::class);
    }
}
