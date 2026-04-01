<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

class SdkEventSubscriberDisabledByPackageFlagTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-trace.enabled', false);
    }

    public function test_sdk_event_subscriber_is_not_registered_when_package_is_disabled(): void
    {
        $this->assertFalse($this->app['events']->hasListeners('Laravel\\Ai\\Events\\PromptingAgent'));
        $this->assertFalse($this->app['events']->hasListeners('Laravel\\Ai\\Events\\ToolInvoked'));
    }
}
