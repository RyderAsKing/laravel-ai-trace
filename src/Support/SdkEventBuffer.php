<?php

namespace RyderAsKing\LaravelAiTrace\Support;

class SdkEventBuffer
{
    /**
     * @var list<array<string, mixed>>
     */
    protected array $events = [];

    /**
     * @param  array<string, mixed>  $event
     */
    public function push(array $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->events;
    }

    public function flush(): void
    {
        $this->events = [];
    }
}
