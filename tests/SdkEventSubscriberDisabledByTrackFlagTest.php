<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

class SdkEventSubscriberDisabledByTrackFlagTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-trace.track_ai_sdk', false);
    }

    public function test_sdk_event_subscriber_is_not_registered_when_track_flag_is_disabled(): void
    {
        $this->assertFalse($this->app['events']->hasListeners('Laravel\\Ai\\Events\\PromptingAgent'));
        $this->assertFalse($this->app['events']->hasListeners('Laravel\\Ai\\Events\\ToolInvoked'));
    }
}
