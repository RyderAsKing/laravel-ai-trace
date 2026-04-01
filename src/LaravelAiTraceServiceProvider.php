<?php

namespace RyderAsKing\LaravelAiTrace;

use RyderAsKing\LaravelAiTrace\Commands\AiTraceSmokeCommand;
use RyderAsKing\LaravelAiTrace\Services\TraceManager;
use Illuminate\Support\ServiceProvider;

class LaravelAiTraceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-trace.php', 'ai-trace');

        $this->app->singleton(TraceManager::class, function ($app): TraceManager {
            return new TraceManager($app['config']->get('ai-trace', []));
        });

        $this->app->alias(TraceManager::class, 'ai-trace');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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
}
