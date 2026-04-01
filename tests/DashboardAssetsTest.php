<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use RuntimeException;
use RyderAsKing\LaravelAiTrace\Support\DashboardAssets;

class DashboardAssetsTest extends TestCase
{
    public function test_dashboard_assets_render_css_and_js_tags(): void
    {
        $assets = $this->app->make(DashboardAssets::class);

        $this->assertStringContainsString('<style>', $assets->css());
        $this->assertStringContainsString('ai-trace-shell', $assets->css());
        $this->assertStringContainsString('<script>', $assets->js());
        $this->assertStringContainsString('window.aiTraceDashboard', $assets->js());
    }

    public function test_dashboard_assets_throw_exception_for_missing_css_file(): void
    {
        $assets = $this->app->make(DashboardAssets::class);

        $assets->css('/tmp/definitely-missing-ai-trace.css');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to load AI Trace dashboard CSS path');

        $assets->css();
    }
}
