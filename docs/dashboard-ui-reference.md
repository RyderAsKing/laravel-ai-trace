# Dashboard UI Reference

This document captures the implemented dashboard UI approach in code, so future UI work can follow the same patterns.

It is based on the current package implementation (not just planning docs), with `docs/dashboard-implementation.md` used as background context.

## Scope and Principles

- Dashboard is package-owned UI (Livewire + Blade components + package assets).
- No runtime dependency on `laravel/pulse`; Pulse is only a design/reference source.
- Dashboard access is host-controlled by gate when defined, with local-only fallback when no gate exists.
- UI reads data through `TraceQueryService` contracts; views do not query models directly.
- Privacy mode (`ai-trace.record_content_mode`) must be respected in drilldown outputs.

## Source of Truth (Verified Files)

- Routing/provider: `src/LaravelAiTraceServiceProvider.php`
- Config + middleware wiring: `config/ai-trace.php`
- Access middleware: `src/Http/Middleware/EnsureDashboardEnabled.php`, `src/Http/Middleware/AuthorizeDashboard.php`
- Asset loader: `src/Support/DashboardAssets.php`
- Query/data contracts: `src/Services/TraceQueryService.php`
- Page views: `resources/views/dashboard.blade.php`, `resources/views/trace-detail.blade.php`
- UI primitives: `resources/views/components/*.blade.php`
- Livewire card classes: `src/Livewire/*.php`
- Livewire card views: `resources/views/livewire/*.blade.php`
- Dashboard JS/CSS: `resources/js/ai-trace.js`, `resources/css/ai-trace.css`
- Build config: `vite.config.js`, `tailwind.config.js`, `package.json`
- Behavior tests: `tests/DashboardFoundationTest.php`, `tests/DashboardCardsTest.php`, `tests/TraceDetailPageTest.php`, `tests/DashboardAssetsTest.php`, `tests/TraceQueryServiceTest.php`

## Current UI Architecture

### 1) Routing and Access

- Base route is named `ai-trace.dashboard` and served by view `ai-trace::dashboard`.
- Trace detail route is `ai-trace.dashboard.trace` at `/traces/{traceId}`.
- Route group domain/path/middleware are configurable via `ai-trace.dashboard.*`.
- Dashboard can be hard-disabled with `ai-trace.dashboard.enabled` (returns 404).
- Authorization behavior:
  - If configured gate exists, it decides access.
  - If gate does not exist, allow only in `local` environment.

### 2) Layout Shell

- Main shell component: `x-ai-trace::layout` (`resources/views/components/layout.blade.php`).
- `controls` slot is used for top-right controls (currently period selector + theme switcher).
- Layout responsibilities:
  - initial theme bootstrapping from `localStorage` key `ai-trace-theme`
  - inlined dashboard CSS/JS injection through `ai-trace-dashboard-assets`
  - Livewire styles/scripts placement
  - 12-column responsive grid container using `default:grid-cols-12`

### 3) UI Primitives

- Card shell: `x-ai-trace::card`
  - uses `cols` prop (1..12) to set `default:lg:col-span-{n}`
  - includes Livewire commit hook to clear loading state
- Header: `x-ai-trace::card-header` (`name`, optional `details`)
- Scrolling region: `x-ai-trace::scroll`
  - overflow container with bottom fade indicator
  - fade indicator uses a semi-opaque flat overlay (`bg-white/90`, `dark:bg-gray-900/90`) and `scaleY` transform (no gradients)
  - includes custom scrollbar utility variants
- Table system: `x-ai-trace::table`, `thead`, `th`, `td`
  - `td` supports `numeric` mode for right-aligned tabular values
- Chart primitive: `x-ai-trace::multi-line-chart`
  - wraps Alpine + Chart.js integration via `window.aiTraceDashboard.multiLineChart(...)`

### 4) Livewire Card Composition Pattern

Implemented cards on dashboard page:

- `TraceVolumeCard` + `trace-volume-card.blade.php`
- `SpanEventsChartCard` + `span-events-chart-card.blade.php`
- `TotalTokensCard` + `total-tokens-card.blade.php`
- `TraceExplorerCard` + `trace-explorer-card.blade.php`
- `WaterfallPreviewCard` + `waterfall-preview-card.blade.php`

Supporting control:

- `PeriodSelector` + `period-selector.blade.php`
  - URL-backed query param alias: `period`
  - dispatches `ai-trace-period-changed`

Shared behavior across cards:

- Read period from request on mount (`period` query param).
- Listen for `ai-trace-period-changed` where period-sensitive.
- Use `wire:poll` intervals on cards for refresh.
- Ask `TraceQueryService` for all display data.

Trace explorer interaction model:

- Status/provider/model filters plus quick filters (`errors only`, minimum duration, minimum tokens).
- Sortable table columns for trace name, status, start time, duration, input tokens, output tokens, and total tokens.
- Dense row presentation with status badges, provider/model grouping, and trace ID visibility.

Note: `ErrorRateCard` exists (`src/Livewire/ErrorRateCard.php`, `resources/views/livewire/error-rate-card.blade.php`) but is not currently registered/rendered in provider/dashboard page.

### 5) Charting and Frontend JS

- Chart runtime lives in `resources/js/ai-trace.js`.
- Uses Chart.js line charts with hidden axes and compact tooltip formatting.
- Time labels are expected in `Y-m-d H:i:s` and formatted client-side with `formatDate`.
- Livewire update path:
  - card render computes fresh `series`
  - on Livewire requests, card dispatches card-specific chart event
  - chart component listens and updates datasets without rebuilding page
- Dataset normalization guards against malformed/null series and provides an empty fallback series.

### 6) Styling and Theme Conventions

- CSS entrypoint: `resources/css/ai-trace.css` (Tailwind base/components/utilities + dashboard utilities).
- Tailwind setup:
  - content scan limited to `resources/views/**/*.blade.php`
  - custom variants include `default`, `scrollbar`, `scrollbar-track`, `scrollbar-thumb`
  - safelist supports dynamic grid/row/col span class generation
  - dark mode strategy is `class`
- Font stack extends Tailwind sans with `Figtree`, loaded via Bunny Fonts in layout.

### 7) Asset Delivery Strategy

- Build output is fixed to:
  - `dist/ai-trace.css`
  - `dist/ai-trace.js`
- `DashboardAssets` reads built files and inlines contents as `<style>` / `<script>` tags.
- Missing asset paths throw runtime exceptions (covered by tests).
- UI PRs that touch JS/CSS should include rebuilt `dist/*` artifacts.

## Detail Page (Drilldown) Pattern

`resources/views/trace-detail.blade.php` composes a trace header and inspector:

- Trace header/metadata
- Interactive trace inspector (left span tree + right tabbed content)

Data comes from `TraceQueryService::traceDetail()` and includes:

- depth-resolved span hierarchy
- computed `bar_percent` per span
- sanitized input/output previews
- sanitized event payloads
- explicit `content_mode` for operator visibility

Detail page waterfall rendering conventions:

- Waterfall bars use a fixed-width track with a filled segment whose inline width is clamped to `0..100` from `bar_percent`.
- A span with `0%` renders empty, and `100%` renders full width (no always-full bars).
- Waterfall bars use flat colors (no gradient fills).

Trace inspector conventions:

- Left pane is the span hierarchy and duration context for fast navigation.
- Right pane uses tabs (`Input`, `Output`, `Events`, `Attributes`, `Raw JSON`) for focused inspection.
- Events shown in the inspector are scoped to the currently selected span.

## URL and Filter State Conventions

- Global period selector: `period`
- Trace explorer filters:
  - `trace_status`
  - `trace_provider`
  - `trace_model`
- Default query values use `except` in Livewire URL attributes to keep URLs clean.

## Testing Baseline for UI Changes

When changing dashboard UI behavior, maintain coverage patterns shown in:

- Route/access behavior (allow/deny/disabled): `tests/DashboardFoundationTest.php`
- Card visibility and card data rendering: `tests/DashboardCardsTest.php`
- Drilldown rendering + privacy behavior: `tests/TraceDetailPageTest.php`
- Asset inlining/missing-file behavior: `tests/DashboardAssetsTest.php`
- Query aggregation/filtering/privacy contracts: `tests/TraceQueryServiceTest.php`

## Recommended Workflow for New UI Work

1. Add/adjust query contract in `TraceQueryService` first.
2. Wire or update Livewire component class in `src/Livewire`.
3. Build UI using existing primitives (`card`, `card-header`, `scroll`, `table`).
4. If charted data is needed, follow existing `multi-line-chart` event pattern.
5. Keep period/filter state URL-backed when it impacts view state.
6. Add/update tests for route access, card rendering, and data/privacy behavior.
7. Rebuild dashboard assets (`npm run build`) so `dist/*` matches sources.

## Guardrails

- Keep dashboard optional via `ai-trace.dashboard.enabled`.
- Keep authorization owned by host app gate definitions.
- Keep dashboard queries behind service contracts; avoid model coupling in Blade/Livewire views.
- Keep privacy modes enforceable end-to-end on drilldown content.
- Keep polling modest and card-scoped.
- Preserve fixed dist filenames and inlined asset delivery unless intentionally redesigning asset strategy.
