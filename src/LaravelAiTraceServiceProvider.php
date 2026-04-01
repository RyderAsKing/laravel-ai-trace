<?php

namespace RyderAsKing\LaravelAiTrace;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;
use RyderAsKing\LaravelAiTrace\Commands\AiTraceSmokeCommand;
use RyderAsKing\LaravelAiTrace\Contracts\NormalizesSdkEvent;
use RyderAsKing\LaravelAiTrace\Listeners\LaravelAiSdkEventSubscriber;
use RyderAsKing\LaravelAiTrace\Livewire\LatencyCard;
use RyderAsKing\LaravelAiTrace\Livewire\PeriodSelector;
use RyderAsKing\LaravelAiTrace\Livewire\SpanEventsChartCard;
use RyderAsKing\LaravelAiTrace\Livewire\TotalTokensCard;
use RyderAsKing\LaravelAiTrace\Livewire\TraceExplorerCard;
use RyderAsKing\LaravelAiTrace\Livewire\TraceVolumeCard;
use RyderAsKing\LaravelAiTrace\Livewire\WaterfallPreviewCard;
use RyderAsKing\LaravelAiTrace\Services\SdkLifecycleManager;
use RyderAsKing\LaravelAiTrace\Services\TraceManager;
use RyderAsKing\LaravelAiTrace\Services\TraceQueryService;
use RyderAsKing\LaravelAiTrace\Support\DashboardAssets;
use RyderAsKing\LaravelAiTrace\Support\PrivacyRedactor;
use RyderAsKing\LaravelAiTrace\Support\SdkCorrelationStore;
use RyderAsKing\LaravelAiTrace\Support\SdkDeduplicator;
use RyderAsKing\LaravelAiTrace\Support\SdkEventBuffer;
use RyderAsKing\LaravelAiTrace\Support\SdkEventNormalizer;

class LaravelAiTraceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-trace.php', 'ai-trace');

        $this->app->singleton(TraceManager::class, function ($app): TraceManager {
            return new TraceManager($app['config']->get('ai-trace', []));
        });

        $this->app->singleton(DashboardAssets::class, fn (): DashboardAssets => new DashboardAssets);
        $this->app->singleton(PrivacyRedactor::class, fn (): PrivacyRedactor => new PrivacyRedactor);
        $this->app->singleton(SdkCorrelationStore::class, fn (): SdkCorrelationStore => new SdkCorrelationStore);
        $this->app->singleton(SdkDeduplicator::class, fn (): SdkDeduplicator => new SdkDeduplicator);
        $this->app->singleton(SdkEventBuffer::class, fn (): SdkEventBuffer => new SdkEventBuffer);
        $this->app->bind(NormalizesSdkEvent::class, SdkEventNormalizer::class);
        $this->app->singleton(SdkLifecycleManager::class);
        $this->app->singleton(LaravelAiSdkEventSubscriber::class);
        $this->app->singleton(TraceQueryService::class, fn (): TraceQueryService => new TraceQueryService);

        $this->app->alias(TraceManager::class, 'ai-trace');
        $this->app->alias(DashboardAssets::class, 'ai-trace-dashboard-assets');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-trace');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'ai-trace');

        $this->registerDashboardLivewireComponents();

        $this->registerDashboardRoutes();

        $this->registerLaravelAiSdkSubscriber();

        $this->publishes([
            __DIR__.'/../config/ai-trace.php' => config_path('ai-trace.php'),
        ], 'ai-trace-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'ai-trace-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AiTraceSmokeCommand::class,
            ]);
        }
    }

    protected function registerDashboardRoutes(): void
    {
        Route::group([
            'domain' => config('ai-trace.dashboard.domain'),
            'prefix' => config('ai-trace.dashboard.path', 'ai-trace'),
            'middleware' => config('ai-trace.dashboard.middleware', ['web']),
        ], function (): void {
            Route::view('/', 'ai-trace::dashboard')->name('ai-trace.dashboard');

            Route::get('/traces/{traceId}', function (string $traceId, TraceQueryService $queryService) {
                return view('ai-trace::trace-detail', [
                    'detail' => $queryService->traceDetail($traceId),
                ]);
            })->name('ai-trace.dashboard.trace');
        });
    }

    protected function registerDashboardLivewireComponents(): void
    {
        $this->callAfterResolving('livewire', function (LivewireManager $livewire): void {
            $livewire->component('ai-trace.trace-volume-card', TraceVolumeCard::class);
            $livewire->component('ai-trace.total-tokens-card', TotalTokensCard::class);
            $livewire->component('ai-trace.latency-card', LatencyCard::class);
            $livewire->component('ai-trace.period-selector', PeriodSelector::class);
            $livewire->component('ai-trace.span-events-chart-card', SpanEventsChartCard::class);
            $livewire->component('ai-trace.trace-explorer-card', TraceExplorerCard::class);
            $livewire->component('ai-trace.waterfall-preview-card', WaterfallPreviewCard::class);
        });
    }

    protected function registerLaravelAiSdkSubscriber(): void
    {
        if (! config('ai-trace.enabled', true) || ! config('ai-trace.track_ai_sdk', true)) {
            return;
        }

        $this->app['events']->subscribe($this->app->make(LaravelAiSdkEventSubscriber::class));
    }
}
