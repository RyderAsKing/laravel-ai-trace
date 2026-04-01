# AI SDK Trace Reference

This document captures the implemented Laravel AI SDK tracing approach in this package.

It is based on current code behavior and tests, not planning-only intent.

## Scope and Principles

- SDK-native instrumentation only (`laravel/ai` events).
- Non-blocking tracing path: instrumentation failures never break host request flow.
- Deterministic correlation and deduplication for repeated or out-of-order callbacks.
- Privacy-first persistence with configurable storage modes and optional host callback.
- Trace fidelity first: lifecycle boundaries, parent-child structure, timeline ordering, and timestamps.

## Source of Truth (Verified Files)

- Provider wiring and subscriber registration: `src/LaravelAiTraceServiceProvider.php`
- SDK subscriber: `src/Listeners/LaravelAiSdkEventSubscriber.php`
- Event normalization contract + implementation: `src/Contracts/NormalizesSdkEvent.php`, `src/Support/SdkEventNormalizer.php`
- Lifecycle orchestration: `src/Services/SdkLifecycleManager.php`
- Correlation state: `src/Support/SdkCorrelationStore.php`
- Dedup state: `src/Support/SdkDeduplicator.php`
- In-memory capture buffer: `src/Support/SdkEventBuffer.php`
- Privacy/redaction engine: `src/Support/PrivacyRedactor.php`
- Trace/span/event persistence APIs: `src/Services/TraceManager.php`
- Config contract: `config/ai-trace.php`
- Behavior tests: `tests/SdkEventSubscriberTest.php`, `tests/SdkEventSubscriberDisabledByPackageFlagTest.php`, `tests/SdkEventSubscriberDisabledByTrackFlagTest.php`, `tests/SdkLifecycleMappingTest.php`, `tests/NonAgentSdkOperationsTest.php`

## Event Subscription and Registration

- SDK subscriber is registered during provider `boot()` only when:
  - `ai-trace.enabled` is truthy
  - `ai-trace.track_ai_sdk` is truthy
- Subscriber listens to agent, tool, failover, stream-complete, and non-agent operation events.
- Subscriber pipeline per event:
  1. lifecycle manager handles persistence/correlation behavior
  2. normalized snapshot is pushed to in-memory event buffer
  3. any thrown exception is swallowed (non-blocking design)

## Correlation Model

- Invocation context map:
  - key: `invocationId`
  - value: `{ trace_id, root_span_id, root_span_key }`
- Tool context map:
  - key: `toolInvocationId`
  - value: numeric span primary key
- Non-agent operation context map:
  - key: `invocationId::operation`
  - value: `{ trace_id, root_span_id, root_span_key }`
- Failover attempts:
  - incremented counter per invocation for attempt metadata

Fallback behavior:

- If a completion event arrives before start context exists, lifecycle manager backfills start context and continues.
- If only persisted rows exist (no in-memory context), context resolution falls back to database lookups by `trace_id` and `span_id`.

## Lifecycle Mapping

Agent lifecycle:

- Start: `PromptingAgent`, `StreamingAgent`
  - creates/loads trace (`trace.trace_id = invocationId`)
  - creates/loads root span (`span.span_id = invocationId`, `span_type = agent`)
  - records `sdk_prompting_agent` or `sdk_streaming_agent`
- Complete: `AgentPrompted`, `AgentStreamed`
  - hydrates usage/meta/output when response exists
  - ingests stream timeline events for streamed completions
  - records completion event (`sdk_agent_prompted`, `sdk_agent_streamed`)
  - ends root span and trace

Tool lifecycle:

- Start: `InvokingTool`
  - creates child tool span (`span.span_id = toolInvocationId`, `span_type = tool`)
  - parent points to root span key (`parent_span_id = invocationId`)
  - records `sdk_invoking_tool`
- Complete: `ToolInvoked`
  - hydrates sanitized result/output
  - records `sdk_tool_invoked`
  - ends tool span

Failover lifecycle:

- `AgentFailedOver`, `ProviderFailedOver`
  - recorded on root span with attempt counter and exception details
  - event types: `sdk_agent_failed_over`, `sdk_provider_failed_over`

Non-agent operation lifecycle:

- Supported families:
  - image generation (`GeneratingImage`, `ImageGenerated`)
  - audio generation (`GeneratingAudio`, `AudioGenerated`)
  - transcription (`GeneratingTranscription`, `TranscriptionGenerated`)
  - embeddings (`GeneratingEmbeddings`, `EmbeddingsGenerated`)
  - reranking (`Reranking`, `Reranked`)
  - files (`StoringFile`, `FileStored`, `FileDeleted`)
  - vector stores (`CreatingStore`, `StoreCreated`, `AddingFileToStore`, `FileAddedToStore`, `RemovingFileFromStore`, `FileRemovedFromStore`, `StoreDeleted`)
- Paired start/end events create and complete operation spans.
- Post-only events (`FileDeleted`, `StoreDeleted`) are modeled as standalone complete operations.

## Event Timeline and Stream Ingestion

- Stream timeline events are read from `response->events` when available on streamed completion.
- Supported behavior:
  - each stream item is converted via `toArray()` when possible
  - `event_type` is derived from payload `type` (fallback: `stream_event`)
  - payload is sanitized except for safe key allowlist
  - `recorded_at` uses source timestamp when numeric timestamp is present
- Volume control:
  - cap: `ai-trace.stream.max_events_per_invocation`
  - when capped, `stream_truncated` event is recorded with `captured/dropped/total/limit`

## Deduplication

- Dedup key is generated from deterministic parts:
  - `event_type`
  - event scope context (invocation/tool/operation/stream metadata)
- Key shape: `sdk-event:{sha1(json_sorted_payload)}`
- TTL window: `ai-trace.dedup_ttl_seconds`
- Duplicate callbacks inside TTL do not create duplicate rows.

## Privacy and Redaction

Storage mode config: `ai-trace.record_content_mode`

- `none`: hides content as `[hidden]`
- `hash`: stores SHA-1 hash of content/scalars
- `redacted` (default): pattern-based redaction then optional callback
- `full`: no built-in masking; optional callback still applied

Built-in redaction patterns:

- email addresses
- token-like values (`sk_*`, `pk_*`, `rk_*`, `tok_*`, `key_*`)
- phone-number-like sequences

Host extension point:

- `ai-trace.redaction.callback` can provide custom string transformation.

## Usage, Model, and Metadata Hydration

- Usage fields are hydrated into span and rolled up to trace totals:
  - `input_tokens`, `output_tokens`, `total_tokens`
  - trace totals: `total_input_tokens`, `total_output_tokens`, `total_tokens`
- Usage extraction source order:
  1. direct response usage object
  2. streamed `stream_end` usage payload aggregation
- Provider/model hydration uses response metadata when missing on start events.
- Finish reason and step count are stored in span meta when available.

## Config Keys Used by SDK Tracing

- `ai-trace.enabled`
- `ai-trace.track_ai_sdk`
- `ai-trace.record_content_mode`
- `ai-trace.redaction.callback`
- `ai-trace.dedup_ttl_seconds`
- `ai-trace.stream.max_events_per_invocation`

## Testing Reference

Recommended commands:

```bash
./vendor/bin/phpunit tests/SdkEventSubscriberTest.php
./vendor/bin/phpunit tests/SdkEventSubscriberDisabledByPackageFlagTest.php
./vendor/bin/phpunit tests/SdkEventSubscriberDisabledByTrackFlagTest.php
./vendor/bin/phpunit tests/SdkLifecycleMappingTest.php
./vendor/bin/phpunit tests/NonAgentSdkOperationsTest.php
```

What those tests verify:

- subscriber registration toggles by config
- normalized in-memory event capture
- agent/tool lifecycle correlation and waterfall integrity
- stream event ingestion, source timestamps, and truncation behavior
- deduplication of repeated callbacks
- failover attempt metadata
- usage/meta hydration and trace token totals
- privacy mode behavior across prompts, outputs, tools, and event payloads
- non-agent operation family coverage
