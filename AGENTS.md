# Laravel AI Trace - Agent Notes

## Repository

- GitHub: `https://github.com/RyderAsKing/laravel-ai-trace.git`
- Package owner: `RyderAsKing`

## Direction

This package is focused on one integration target: Laravel AI SDK (`laravel/ai`).

- No HTTP client fallback instrumentation
- SDK-native events and lifecycle hooks only
- Trace fidelity first, then higher-level observability outputs

## Build Priorities

### First: main tracing features

1. Trace and span lifecycle persistence
2. Parent-child waterfall graph integrity
3. Event timeline capture for retries/tool calls/streaming milestones
4. Deduplication and timestamp reliability
5. Privacy and redaction controls

### Then: observability outputs

- Custom dashboard for trace exploration
- Dashboard authorization controlled by the host app (for example gate definitions in `AppServiceProvider`)
- Laravel Pulse cards for AI trace metrics and activity summaries

### Last: cost engine

- Model pricing catalog and overrides
- Token/cost rollups per span and trace
- Budget alerting and spend thresholds

## Core Concepts

- `Trace`: one end-to-end AI workflow
- `Span`: one operation in the workflow (agent call, tool call, nested step)
- `Event`: fine-grained timeline markers within a span

## Architecture

### Collection Layer

- Laravel AI SDK listeners/adapters only
- Track prompt lifecycle, tool lifecycle, streaming events, retries, and failures

### Domain Layer

- Trace manager
- Span/event coordinator
- Deduplicator
- Provider/model normalizer
- Privacy/redaction engine

### Storage Layer

- Eloquent models + migrations for traces, spans, and events
- Pricing/budgets added when cost engine work begins

### Query/Presentation Layer

- Waterfall tree builders
- Trace query services
- Custom dashboard + Pulse card data sources

## Security and Privacy

- Safe-by-default content handling
- Storage modes: `none`, `hash`, `redacted`, `full`
- Built-in sensitive pattern redaction + custom callbacks
- Configurable retention policy

## Versioning Approach

The package starts at `v1` and evolves incrementally (`v1.1`, `v1.2`, ...).

- No "v2 rewrite" planning language
- Continuous improvements inside the `v1.x` line

## Local Development Notes

- Primary external reference SDK repository: `references/laravel-ai`
- Dashboard/UI layout reference repository: `references/laravel-pulse`
- We keep local clones of `https://github.com/laravel/ai.git` and `https://github.com/laravel/pulse.git` for instrumentation and dashboard layout research
- That `references` directory is gitignored in this repository

## Current Docs

- Build plan: `docs/laravel-ai-trace.md`
- Package development notes: `docs/package-development-notes.md`
- Laravel AI SDK trace reference: `docs/ai-sdk-trace-reference.md`
- Upstream Laravel AI SDK docs snapshot: `docs/laravel-ai-sdk.md`
