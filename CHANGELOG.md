# Changelog

All notable changes to this package are documented in this file.

## [1.0.5] - 2026-04-02

### Documentation

- Reworked `README.md` into a production-ready guide with installation, authorization, configuration, routes, and architecture details.
- Added updated dashboard and trace detail screenshots (`dashboard.webp`, `trace.webp`) and removed the old preview image.
- Updated compatibility documentation to reflect Laravel `12+`, PHP `8.3+`, and Livewire `3.6+` (including `4.x`).

### Compatibility

- Expanded package framework constraints to support both Illuminate `^12.0` and `^13.0`.

### Notes

- Clarified release history: an earlier commit subject referenced `v1.0.1`, while the actual tag for this release is `v1.0.5`.

## [1.0.4] - 2026-04-02

### Added

- Enhanced trace detail inspector with token usage metrics and event previews for faster drilldown analysis.

### Changed

- Updated trace detail data shaping in `TraceQueryService` to support the new inspector presentation.
- Rebuilt dashboard CSS assets to match updated trace detail UI.

## [1.0.3] - 2026-04-02

### Compatibility

- Added support for Livewire `4.x` while keeping existing `3.6+` support.

## [1.0.2] - 2026-04-02

### Changed

- Rebuilt published dashboard CSS assets after the scroll overlay hotfix to keep `dist/*` aligned with source views.

## [1.0.1] - 2026-04-02

### Fixed

- Fixed scroll fade overlay behavior so it no longer leaks into the viewport outside the scroll container.

### Changed

- Merged the dashboard scroll overlay hotfix into `main`.

## [1.0.0] - 2026-04-02

Initial private release of Laravel AI Trace.

### Added

- Laravel AI SDK-native tracing pipeline with persisted `trace -> spans -> events` lifecycle capture.
- Event normalization and buffering for prompt/tool/retry/stream milestones from SDK events.
- Trace orchestration services for starting, updating, and finalizing traces and spans.
- Eloquent storage layer with migrations and models for traces, spans, and span events.
- Privacy-aware content handling with configurable modes (`none`, `hash`, `redacted`, `full`) and redaction callback support.
- Deduplication controls and correlation helpers to improve timestamp/event reliability.
- Retention and operational config settings for tracing, dashboard, Pulse integration, pricing sync toggles, and alerts.
- Dashboard routing and middleware guards for enablement + authorization gate control.
- AI trace dashboard with period selector and core cards:
  - Trace Volume trend
  - Span Events trend
  - Total Tokens card with stacked input/output bars and reduced period-aware bucket density
  - Trace Explorer table with status/provider/model filters, quick filters, and sortable token/latency/time columns
  - Waterfall Preview summary
- Trace detail inspector with split layout:
  - Left pane span hierarchy with duration/token context
  - Right pane tabbed inspection (`Input`, `Output`, `Events`, `Attributes`, `Raw JSON`) scoped to selected span
- Frontend dashboard assets bundle for Livewire + chart rendering.
- Package smoke command for local validation workflows.

### Testing

- Added and expanded test coverage for dashboard rendering, dashboard foundation wiring, trace detail rendering, query service behavior, and chart/dataset contracts.
- Current suite status for this release: `30 tests`, `169 assertions`, passing.
