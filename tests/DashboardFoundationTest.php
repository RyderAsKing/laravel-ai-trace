<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use Illuminate\Support\Facades\Gate;

class DashboardFoundationTest extends TestCase
{
    public function test_dashboard_route_renders_when_gate_allows_access(): void
    {
        Gate::define('viewAiTrace', fn ($user = null) => true);

        $response = $this->get(route('ai-trace.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Laravel AI Trace')
            ->assertSee('Trace Volume')
            ->assertSee('ai-trace-shell', false)
            ->assertSee('window.aiTraceDashboard', false);
    }

    public function test_dashboard_route_is_forbidden_when_gate_denies_access(): void
    {
        Gate::define('viewAiTrace', fn ($user = null) => false);

        $this->get(route('ai-trace.dashboard'))->assertForbidden();
    }

    public function test_dashboard_route_is_forbidden_without_gate_in_non_local_environment(): void
    {
        $this->app['env'] = 'production';

        $this->get(route('ai-trace.dashboard'))->assertForbidden();
    }

    public function test_dashboard_route_is_unavailable_when_disabled(): void
    {
        Gate::define('viewAiTrace', fn ($user = null) => true);
        config()->set('ai-trace.dashboard.enabled', false);

        $this->get(route('ai-trace.dashboard'))->assertNotFound();
    }
}
