<?php

namespace RyderAsKing\LaravelAiTrace\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\SpanEvent;
use RyderAsKing\LaravelAiTrace\Models\Trace;

class TraceQueryService
{
    public function periodLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' minutes';
        }

        if ($minutes < 1440) {
            $hours = (int) round($minutes / 60);

            return $hours.' hour'.($hours === 1 ? '' : 's');
        }

        $days = (int) round($minutes / 1440);

        return $days.' day'.($days === 1 ? '' : 's');
    }

    public function traceFilters(int $minutes = 60): array
    {
        $since = $this->windowStart($minutes);

        return [
            'statuses' => Trace::query()
                ->where('started_at', '>=', $since)
                ->select('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->filter()
                ->values(),
            'providers' => Span::query()
                ->where('started_at', '>=', $since)
                ->whereNotNull('provider')
                ->select('provider')
                ->distinct()
                ->orderBy('provider')
                ->pluck('provider')
                ->filter()
                ->values(),
            'models' => Span::query()
                ->where('started_at', '>=', $since)
                ->whereNotNull('model_normalized')
                ->select('model_normalized')
                ->distinct()
                ->orderBy('model_normalized')
                ->pluck('model_normalized')
                ->filter()
                ->values(),
        ];
    }

    public function filteredTraces(array $filters = [], int $limit = 30): Collection
    {
        $minutes = max(1, (int) ($filters['minutes'] ?? 60));
        $status = (string) ($filters['status'] ?? 'all');
        $provider = (string) ($filters['provider'] ?? 'all');
        $model = (string) ($filters['model'] ?? 'all');
        $errorOnly = (bool) ($filters['error_only'] ?? false);
        $minDurationMs = max(0, (int) ($filters['min_duration_ms'] ?? 0));
        $minTokens = max(0, (int) ($filters['min_tokens'] ?? 0));
        $sortBy = (string) ($filters['sort_by'] ?? 'started_at');
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $since = $this->windowStart($minutes);

        $sortableColumns = [
            'started_at',
            'name',
            'status',
            'duration_ms',
            'total_tokens',
            'total_input_tokens',
            'total_output_tokens',
        ];

        if (! in_array($sortBy, $sortableColumns, true)) {
            $sortBy = 'started_at';
        }

        $query = Trace::query()
            ->where('started_at', '>=', $since)
            ->with([
                'spans' => function ($spanQuery): void {
                    $spanQuery
                        ->select('id', 'trace_id', 'provider', 'model_normalized', 'started_at')
                        ->orderBy('started_at');
                },
            ])
            ->orderBy($sortBy, $sortDirection)
            ->orderByDesc('started_at')
            ->limit(max(1, min(100, $limit)));

        if ($errorOnly) {
            $query->where('status', 'error');
        }

        if ($minDurationMs > 0) {
            $query->where('duration_ms', '>=', $minDurationMs);
        }

        if ($minTokens > 0) {
            $query->where('total_tokens', '>=', $minTokens);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($provider !== 'all') {
            $query->whereHas('spans', function ($spanQuery) use ($provider): void {
                $spanQuery->where('provider', $provider);
            });
        }

        if ($model !== 'all') {
            $query->whereHas('spans', function ($spanQuery) use ($model): void {
                $spanQuery->where('model_normalized', $model);
            });
        }

        return $query->get()->map(function (Trace $trace): array {
            $primarySpan = $trace->spans
                ->first(fn (Span $span): bool => ! empty($span->provider) || ! empty($span->model_normalized));

            return [
                'trace_id' => $trace->trace_id,
                'name' => $trace->name,
                'status' => $trace->status,
                'duration_ms' => $trace->duration_ms,
                'total_tokens' => $trace->total_tokens,
                'input_tokens' => $trace->total_input_tokens,
                'output_tokens' => $trace->total_output_tokens,
                'started_at' => $trace->started_at,
                'provider' => $primarySpan?->provider,
                'model' => $primarySpan?->model_normalized,
            ];
        });
    }

    public function traceDetail(string $traceId, int $spanLimit = 300, int $eventLimit = 500): array
    {
        $trace = Trace::query()->where('trace_id', $traceId)->firstOrFail();
        $spans = Span::query()
            ->where('trace_id', $trace->id)
            ->orderBy('started_at')
            ->limit(max(1, min(1000, $spanLimit)))
            ->get();

        $maxDuration = max(1, (int) $spans->max('duration_ms'));
        $spanIdLookup = $spans->keyBy('span_id');
        $depthLookup = [];
        $idToName = $spans->mapWithKeys(fn (Span $span): array => [$span->id => $span->name ?: $span->span_id]);

        foreach ($spans as $span) {
            $depthLookup[$span->span_id] = $this->resolveDepth($span, $spanIdLookup);
        }

        $events = SpanEvent::query()
            ->where('trace_id', $trace->id)
            ->orderBy('recorded_at')
            ->limit(max(1, min(2000, $eventLimit)))
            ->get(['span_id', 'event_type', 'payload', 'recorded_at']);

        return [
            'trace' => $trace,
            'spans' => $spans->map(function (Span $span) use ($depthLookup, $maxDuration): array {
                return [
                    'id' => (int) $span->id,
                    'span_id' => $span->span_id,
                    'parent_span_id' => $span->parent_span_id,
                    'name' => $span->name ?: $span->span_id,
                    'span_type' => $span->span_type,
                    'status' => $span->status,
                    'provider' => $span->provider,
                    'model' => $span->model_normalized,
                    'duration_ms' => (int) ($span->duration_ms ?? 0),
                    'input_tokens' => (int) ($span->input_tokens ?? 0),
                    'output_tokens' => (int) ($span->output_tokens ?? 0),
                    'total_tokens' => (int) ($span->total_tokens ?? 0),
                    'depth' => $depthLookup[$span->span_id] ?? 0,
                    'bar_percent' => round(((int) ($span->duration_ms ?? 0) / $maxDuration) * 100, 2),
                    'started_at' => $span->started_at,
                    'meta' => $span->meta,
                    'input_preview' => $this->sanitizeText($span->input_text),
                    'output_preview' => $this->sanitizeText($span->output_text),
                ];
            })->values(),
            'events' => $events->map(function (SpanEvent $event) use ($idToName): array {
                return [
                    'span_id' => (int) $event->span_id,
                    'event_type' => $event->event_type,
                    'span_name' => $idToName[$event->span_id] ?? 'unknown',
                    'recorded_at' => $event->recorded_at,
                    'payload' => $this->sanitizePayload($event->payload),
                ];
            })->values(),
            'content_mode' => (string) config('ai-trace.record_content_mode', 'redacted'),
        ];
    }

    public function traceVolume(int $minutes = 60): array
    {
        $since = $this->windowStart($minutes);
        $total = Trace::query()
            ->where('started_at', '>=', $since)
            ->count();

        return [
            'minutes' => $minutes,
            'period_label' => $this->periodLabel($minutes),
            'total' => $total,
            'since' => $since,
        ];
    }

    public function errorRate(int $minutes = 60): array
    {
        $since = $this->windowStart($minutes);
        $baseQuery = Trace::query()->where('started_at', '>=', $since);
        $total = (clone $baseQuery)->count();
        $errors = (clone $baseQuery)->where('status', 'error')->count();
        $rate = $total > 0 ? round(($errors / $total) * 100, 2) : 0.0;

        return [
            'minutes' => $minutes,
            'period_label' => $this->periodLabel($minutes),
            'total' => $total,
            'errors' => $errors,
            'rate' => $rate,
            'since' => $since,
        ];
    }

    public function latency(int $minutes = 60): array
    {
        $since = $this->windowStart($minutes);
        $durations = Trace::query()
            ->where('started_at', '>=', $since)
            ->whereNotNull('duration_ms')
            ->orderBy('duration_ms')
            ->pluck('duration_ms')
            ->values();

        if ($durations->isEmpty()) {
            return [
                'minutes' => $minutes,
                'period_label' => $this->periodLabel($minutes),
                'count' => 0,
                'avg' => 0.0,
                'p50' => 0,
                'p95' => 0,
                'max' => 0,
                'since' => $since,
            ];
        }

        return [
            'minutes' => $minutes,
            'period_label' => $this->periodLabel($minutes),
            'count' => $durations->count(),
            'avg' => round((float) $durations->avg(), 2),
            'p50' => $this->percentile($durations, 0.50),
            'p95' => $this->percentile($durations, 0.95),
            'max' => (int) $durations->last(),
            'since' => $since,
        ];
    }

    public function totalTokens(int $minutes = 60): array
    {
        $since = $this->windowStart($minutes);
        $traces = Trace::query()
            ->where('started_at', '>=', $since)
            ->get(['total_tokens', 'total_input_tokens', 'total_output_tokens']);

        $input = 0;
        $output = 0;
        $total = 0;

        foreach ($traces as $trace) {
            $inputTokens = (int) ($trace->total_input_tokens ?? 0);
            $outputTokens = (int) ($trace->total_output_tokens ?? 0);
            $traceTotal = (int) ($trace->total_tokens ?? 0);

            if ($inputTokens === 0 && $outputTokens === 0 && $traceTotal > 0) {
                $outputTokens = $traceTotal;
            }

            $input += $inputTokens;
            $output += $outputTokens;
            $total += ($inputTokens + $outputTokens);
        }

        return [
            'minutes' => $minutes,
            'period_label' => $this->periodLabel($minutes),
            'input_total' => $input,
            'output_total' => $output,
            'total' => $total,
            'since' => $since,
        ];
    }

    public function recentTraces(int $limit = 10): Collection
    {
        return Trace::query()
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get([
                'trace_id',
                'name',
                'status',
                'duration_ms',
                'total_tokens',
                'total_cost_usd',
                'started_at',
            ]);
    }

    public function waterfallPreview(int $traceLimit = 1, int $spanLimit = 50): Collection
    {
        $traces = Trace::query()
            ->orderByDesc('started_at')
            ->limit($traceLimit)
            ->get(['id', 'trace_id', 'name', 'status', 'started_at']);

        return $traces->map(function (Trace $trace) use ($spanLimit): array {
            $spans = Span::query()
                ->where('trace_id', $trace->id)
                ->orderBy('started_at')
                ->limit($spanLimit)
                ->get(['span_id', 'parent_span_id', 'name', 'span_type', 'status', 'duration_ms', 'started_at']);

            return [
                'trace_id' => $trace->trace_id,
                'name' => $trace->name,
                'status' => $trace->status,
                'started_at' => $trace->started_at,
                'span_count' => $spans->count(),
                'spans' => $spans,
            ];
        });
    }

    protected function windowStart(int $minutes): Carbon
    {
        return now()->subMinutes(max(1, $minutes));
    }

    public function traceStatusSeries(int $minutes = 30, int $points = 30): array
    {
        $points = max(12, min(96, $points));
        $windowSeconds = max(60, $minutes * 60);
        $bucketSeconds = max(60, (int) ceil($windowSeconds / $points));
        $start = now()->subSeconds($bucketSeconds * $points);

        $traces = Trace::query()
            ->where('started_at', '>=', $start)
            ->get(['status', 'started_at']);

        return $this->buildGroupedSeries(
            $traces,
            $start,
            $bucketSeconds,
            $points,
            fn (Trace $trace): string => $trace->status ?: 'unknown',
            ['ok', 'error', 'cancelled', 'failed', 'unknown']
        );
    }

    public function spanEventTypeSeries(int $minutes = 30, int $points = 30): array
    {
        $points = max(12, min(96, $points));
        $windowSeconds = max(60, $minutes * 60);
        $bucketSeconds = max(60, (int) ceil($windowSeconds / $points));
        $start = now()->subSeconds($bucketSeconds * $points);

        $events = SpanEvent::query()
            ->where('recorded_at', '>=', $start)
            ->get(['event_type', 'recorded_at']);

        return $this->buildGroupedSeries(
            $events,
            $start,
            $bucketSeconds,
            $points,
            fn (SpanEvent $event): string => $event->event_type ?: 'unknown',
            ['stream_chunk', 'tool_start', 'tool_end', 'retry', 'annotation', 'system', 'unknown']
        );
    }

    public function tokenUsageSeries(int $minutes = 30, ?int $points = null): array
    {
        $points = $points ?? max(6, min(24, (int) ceil($minutes / 5)));
        $windowSeconds = max(60, $minutes * 60);
        $bucketSeconds = max(60, (int) ceil($windowSeconds / $points));
        $start = now()->subSeconds($bucketSeconds * $points);
        $startTimestamp = $start->getTimestamp();

        $labels = [];
        for ($index = 0; $index < $points; $index++) {
            $labels[] = $start->copy()->addSeconds($bucketSeconds * $index)->format('Y-m-d H:i:s');
        }

        $inputPoints = array_fill(0, $points, 0);
        $outputPoints = array_fill(0, $points, 0);

        $traces = Trace::query()
            ->where('started_at', '>=', $start)
            ->get(['started_at', 'total_tokens', 'total_input_tokens', 'total_output_tokens']);

        foreach ($traces as $trace) {
            $timestamp = $trace->started_at;

            if (! $timestamp) {
                continue;
            }

            $offset = $timestamp->getTimestamp() - $startTimestamp;

            if ($offset < 0) {
                continue;
            }

            $bucket = (int) floor($offset / $bucketSeconds);

            if ($bucket < 0 || $bucket >= $points) {
                continue;
            }

            $inputTokens = (int) ($trace->total_input_tokens ?? 0);
            $outputTokens = (int) ($trace->total_output_tokens ?? 0);
            $traceTotal = (int) ($trace->total_tokens ?? 0);

            if ($inputTokens === 0 && $outputTokens === 0 && $traceTotal > 0) {
                $outputTokens = $traceTotal;
            }

            $inputPoints[$bucket] += $inputTokens;
            $outputPoints[$bucket] += $outputTokens;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'key' => 'input',
                    'label' => 'input',
                    'points' => $inputPoints,
                ],
                [
                    'key' => 'output',
                    'label' => 'output',
                    'points' => $outputPoints,
                ],
            ],
            'total' => array_sum($inputPoints) + array_sum($outputPoints),
        ];
    }

    protected function buildGroupedSeries(
        Collection $records,
        Carbon $start,
        int $bucketSeconds,
        int $points,
        callable $groupResolver,
        array $preferredOrder = []
    ): array
    {
        $labels = [];
        $groupValues = [];
        $startTimestamp = $start->getTimestamp();
        $typeField = $records->first() instanceof SpanEvent ? 'recorded_at' : 'started_at';

        for ($index = 0; $index < $points; $index++) {
            $labels[] = $start->copy()->addSeconds($bucketSeconds * $index)->format('Y-m-d H:i:s');
        }

        foreach ($records as $record) {
            $timestamp = data_get($record, $typeField);

            if (! $timestamp) {
                continue;
            }

            $time = $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp);
            $offset = $time->getTimestamp() - $startTimestamp;

            if ($offset < 0) {
                continue;
            }

            $bucket = (int) floor($offset / $bucketSeconds);

            if ($bucket < 0 || $bucket >= $points) {
                continue;
            }

            $group = (string) $groupResolver($record);

            if (! isset($groupValues[$group])) {
                $groupValues[$group] = array_fill(0, $points, 0);
            }

            $groupValues[$group][$bucket]++;
        }

        $orderedGroups = collect($groupValues)
            ->keys()
            ->sortBy(function (string $group) use ($preferredOrder): array {
                $index = array_search($group, $preferredOrder, true);

                return [$index === false ? 999 : $index, $group];
            })
            ->values();

        $datasets = $orderedGroups
            ->map(fn (string $group): array => [
                'key' => $group,
                'label' => str_replace('_', ' ', $group),
                'points' => $groupValues[$group],
            ])
            ->values()
            ->all();

        if ($datasets === []) {
            $datasets[] = [
                'key' => 'empty',
                'label' => 'no data',
                'points' => array_fill(0, $points, 0),
            ];
        }

        $total = collect($datasets)->sum(fn (array $dataset): int => array_sum($dataset['points']));

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'total' => $total,
        ];
    }

    protected function percentile(Collection $values, float $percentile): int
    {
        $index = (int) ceil($values->count() * $percentile) - 1;

        return (int) $values->get(max(0, $index), 0);
    }

    protected function resolveDepth(Span $span, Collection $spanLookup): int
    {
        $depth = 0;
        $cursor = $span;

        while ($cursor->parent_span_id !== null && $spanLookup->has($cursor->parent_span_id)) {
            $depth++;
            $cursor = $spanLookup->get($cursor->parent_span_id);

            if ($depth > 50) {
                break;
            }
        }

        return $depth;
    }

    protected function sanitizeText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return match ((string) config('ai-trace.record_content_mode', 'redacted')) {
            'none' => '[hidden]',
            'hash' => sha1($value),
            'full' => $value,
            default => $this->redact($value),
        };
    }

    protected function sanitizePayload(?array $payload): array|string|null
    {
        if ($payload === null) {
            return null;
        }

        $mode = (string) config('ai-trace.record_content_mode', 'redacted');

        if ($mode === 'none') {
            return '[hidden]';
        }

        if ($mode === 'hash') {
            return sha1((string) json_encode($payload));
        }

        if ($mode === 'full') {
            return $payload;
        }

        return collect($payload)->map(function ($value) {
            if (is_array($value)) {
                return $this->sanitizePayload($value);
            }

            if (is_string($value)) {
                return $this->redact($value);
            }

            return $value;
        })->toArray();
    }

    protected function redact(string $text): string
    {
        $redacted = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $text) ?? $text;
        $redacted = preg_replace('/(sk|pk|rk|tok|key)_[A-Za-z0-9\-_]{8,}/i', '[redacted-token]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\+?[0-9][0-9\-\s]{7,}[0-9]/', '[redacted-phone]', $redacted) ?? $redacted;

        return $redacted;
    }
}
