<?php

namespace RyderAsKing\LaravelAiTrace\Commands;

use RyderAsKing\LaravelAiTrace\Services\TraceManager;
use Illuminate\Console\Command;

class AiTraceSmokeCommand extends Command
{
    protected $signature = 'ai-trace:smoke';

    protected $description = 'Create a smoke-test trace with child spans';

    public function handle(TraceManager $traceManager): int
    {
        $trace = $traceManager->startTrace([
            'name' => 'ai-trace smoke test',
            'status' => 'ok',
        ]);

        $rootSpan = $traceManager->startSpan($trace, [
            'name' => 'agent.prompt',
            'span_type' => 'agent',
            'status' => 'ok',
        ]);

        $llmSpan = $traceManager->startSpan($trace, [
            'name' => 'llm.generate',
            'span_type' => 'llm',
            'parent_span_id' => $rootSpan->span_id,
            'status' => 'ok',
        ]);

        $traceManager->recordEvent($trace, $llmSpan, 'annotation', [
            'message' => 'LLM span created for smoke verification.',
        ]);

        $toolSpan = $traceManager->startSpan($trace, [
            'name' => 'tool.lookup',
            'span_type' => 'tool',
            'parent_span_id' => $rootSpan->span_id,
            'status' => 'ok',
        ]);

        $traceManager->recordEvent($trace, $toolSpan, 'tool_start', [
            'tool' => 'example_tool',
        ]);

        $traceManager->recordEvent($trace, $toolSpan, 'tool_end', [
            'tool' => 'example_tool',
            'result' => 'ok',
        ]);

        $traceManager->endSpan($llmSpan);
        $traceManager->endSpan($toolSpan);
        $traceManager->endSpan($rootSpan);
        $traceManager->endTrace($trace);

        $this->info('Smoke trace created successfully.');
        $this->line('trace_id: '.$trace->fresh()->trace_id);

        return self::SUCCESS;
    }
}
