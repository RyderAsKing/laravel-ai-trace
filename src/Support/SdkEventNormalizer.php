<?php

namespace RyderAsKing\LaravelAiTrace\Support;

use RyderAsKing\LaravelAiTrace\Contracts\NormalizesSdkEvent;
use Throwable;

class SdkEventNormalizer implements NormalizesSdkEvent
{
    public function normalize(string $eventName, mixed $event): array
    {
        return [
            'event_name' => $eventName,
            'event_short_name' => $this->shortName($eventName),
            'captured_at' => (new \DateTimeImmutable)->format(DATE_ATOM),
            'invocation_id' => $this->extractScalar($event, 'invocationId'),
            'tool_invocation_id' => $this->extractScalar($event, 'toolInvocationId'),
            'provider' => $this->extractScalar($event, 'provider'),
            'model' => $this->extractScalar($event, 'model'),
            'payload' => $this->extractPayload($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractPayload(mixed $event): array
    {
        if (! is_object($event)) {
            return ['raw' => $event];
        }

        $payload = [];

        foreach (['invocationId', 'toolInvocationId', 'provider', 'model'] as $field) {
            $value = $this->extractScalar($event, $field);

            if ($value !== null) {
                $payload[$field] = $value;
            }
        }

        $exception = $this->extractField($event, 'exception');

        if ($exception instanceof Throwable) {
            $payload['exception'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        return $payload;
    }

    protected function extractScalar(mixed $event, string $field): string|int|float|bool|null
    {
        $value = $this->extractField($event, $field);

        if (is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    protected function shortName(string $eventName): string
    {
        $parts = explode('\\', $eventName);

        return (string) end($parts);
    }

    protected function extractField(mixed $event, string $field): mixed
    {
        if (is_array($event)) {
            return $event[$field] ?? null;
        }

        if (! is_object($event) || ! isset($event->{$field})) {
            return null;
        }

        return $event->{$field};
    }
}
