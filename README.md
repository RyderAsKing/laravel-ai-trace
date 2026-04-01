# Laravel AI Trace

Laravel AI Trace is a Laravel package for LangSmith-style tracing of AI workflows built with `laravel/ai`.

## What It Does

- Captures end-to-end traces as `trace -> spans -> events`
- Records timing, ordering, retries, failures, and execution milestones
- Preserves parent-child span relationships for waterfall visualization
- Supports privacy controls for stored content (including redaction modes)
- Provides data foundations for dashboard and Pulse-based observability views

## What It Does Not Do

- It does not implement generic HTTP-level AI instrumentation
- It does not target non-`laravel/ai` runtimes as a primary integration path
- It does not prioritize cost/budget analytics ahead of trace fidelity
