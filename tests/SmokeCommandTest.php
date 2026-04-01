<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\SpanEvent;
use RyderAsKing\LaravelAiTrace\Models\Trace;

class SmokeCommandTest extends TestCase
{
    public function test_smoke_command_creates_trace_data(): void
    {
        $this->artisan('ai-trace:smoke')->assertSuccessful();

        $this->assertSame(1, Trace::query()->count());
        $this->assertSame(3, Span::query()->count());
        $this->assertSame(3, SpanEvent::query()->count());
    }
}
