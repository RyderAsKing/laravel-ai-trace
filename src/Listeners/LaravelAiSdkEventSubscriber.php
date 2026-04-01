<?php

namespace RyderAsKing\LaravelAiTrace\Listeners;

use RyderAsKing\LaravelAiTrace\Contracts\NormalizesSdkEvent;
use RyderAsKing\LaravelAiTrace\Services\SdkLifecycleManager;
use RyderAsKing\LaravelAiTrace\Support\SdkEventBuffer;
use Throwable;

class LaravelAiSdkEventSubscriber
{
    /**
     * @var list<string>
     */
    public const EVENT_NAMES = [
        'Laravel\\Ai\\Events\\PromptingAgent',
        'Laravel\\Ai\\Events\\AgentPrompted',
        'Laravel\\Ai\\Events\\StreamingAgent',
        'Laravel\\Ai\\Events\\AgentStreamed',
        'Laravel\\Ai\\Events\\InvokingTool',
        'Laravel\\Ai\\Events\\ToolInvoked',
        'Laravel\\Ai\\Events\\AgentFailedOver',
        'Laravel\\Ai\\Events\\GeneratingImage',
        'Laravel\\Ai\\Events\\ImageGenerated',
        'Laravel\\Ai\\Events\\GeneratingAudio',
        'Laravel\\Ai\\Events\\AudioGenerated',
        'Laravel\\Ai\\Events\\GeneratingTranscription',
        'Laravel\\Ai\\Events\\TranscriptionGenerated',
        'Laravel\\Ai\\Events\\GeneratingEmbeddings',
        'Laravel\\Ai\\Events\\EmbeddingsGenerated',
        'Laravel\\Ai\\Events\\Reranking',
        'Laravel\\Ai\\Events\\Reranked',
        'Laravel\\Ai\\Events\\StoringFile',
        'Laravel\\Ai\\Events\\FileStored',
        'Laravel\\Ai\\Events\\FileDeleted',
        'Laravel\\Ai\\Events\\CreatingStore',
        'Laravel\\Ai\\Events\\StoreCreated',
        'Laravel\\Ai\\Events\\AddingFileToStore',
        'Laravel\\Ai\\Events\\FileAddedToStore',
        'Laravel\\Ai\\Events\\RemovingFileFromStore',
        'Laravel\\Ai\\Events\\FileRemovedFromStore',
        'Laravel\\Ai\\Events\\StoreDeleted',
        'Laravel\\Ai\\Events\\ProviderFailedOver',
    ];

    public function __construct(
        protected NormalizesSdkEvent $normalizer,
        protected SdkEventBuffer $eventBuffer,
        protected SdkLifecycleManager $lifecycleManager,
    ) {
    }

    public function subscribe($events): void
    {
        foreach (self::EVENT_NAMES as $eventName) {
            $events->listen($eventName, function (mixed $event) use ($eventName): void {
                $this->handle($eventName, $event);
            });
        }
    }

    public function handle(string $eventName, mixed $event): void
    {
        try {
            $this->lifecycleManager->handle($eventName, $event);
            $this->eventBuffer->push($this->normalizer->normalize($eventName, $event));
        } catch (Throwable) {
            // Non-blocking by design: tracing must not break host flow.
        }
    }
}
