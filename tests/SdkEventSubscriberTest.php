<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use RyderAsKing\LaravelAiTrace\Listeners\LaravelAiSdkEventSubscriber;
use RyderAsKing\LaravelAiTrace\Support\SdkEventBuffer;

class SdkEventSubscriberTest extends TestCase
{
    public function test_sdk_event_subscriber_is_registered_when_tracking_enabled(): void
    {
        $this->assertTrue($this->app['events']->hasListeners('Laravel\\Ai\\Events\\PromptingAgent'));
        $this->assertTrue($this->app['events']->hasListeners('Laravel\\Ai\\Events\\ToolInvoked'));
        $this->assertTrue($this->app['events']->hasListeners('Laravel\\Ai\\Events\\ProviderFailedOver'));

        $this->assertGreaterThan(20, count(LaravelAiSdkEventSubscriber::EVENT_NAMES));
    }

    public function test_sdk_event_subscriber_captures_normalized_event_in_memory(): void
    {
        $buffer = $this->app->make(SdkEventBuffer::class);
        $buffer->flush();

        $event = new \stdClass;
        $event->invocationId = 'inv-123';
        $event->toolInvocationId = 'tool-456';
        $event->provider = 'openai';
        $event->model = 'gpt-4o-mini';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$event]);

        $captured = $buffer->all();

        $this->assertCount(1, $captured);
        $this->assertSame('Laravel\\Ai\\Events\\PromptingAgent', $captured[0]['event_name']);
        $this->assertSame('PromptingAgent', $captured[0]['event_short_name']);
        $this->assertSame('inv-123', $captured[0]['invocation_id']);
        $this->assertSame('tool-456', $captured[0]['tool_invocation_id']);
        $this->assertSame('openai', $captured[0]['provider']);
        $this->assertSame('gpt-4o-mini', $captured[0]['model']);
    }
}
