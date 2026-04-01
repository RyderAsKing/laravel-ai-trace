<?php

namespace RyderAsKing\LaravelAiTrace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpanEvent extends Model
{
    protected $table = 'ai_span_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function trace(): BelongsTo
    {
        return $this->belongsTo(Trace::class);
    }

    public function span(): BelongsTo
    {
        return $this->belongsTo(Span::class);
    }
}
