<?php

namespace RyderAsKing\LaravelAiTrace\Contracts;

interface NormalizesSdkEvent
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(string $eventName, mixed $event): array;
}
