<?php

namespace RyderAsKing\LaravelAiTrace\Support;

class SdkCorrelationStore
{
    /**
     * @var array<string, array{trace_id:int, root_span_id:int, root_span_key:string}>
     */
    protected array $invocations = [];

    /**
     * @var array<string, int>
     */
    protected array $tools = [];

    /**
     * @var array<string, array{trace_id:int, root_span_id:int, root_span_key:string}>
     */
    protected array $operations = [];

    /**
     * @var array<string, int>
     */
    protected array $failoverAttempts = [];

    protected ?string $latestInvocationId = null;

    /**
     * @param  array{trace_id:int, root_span_id:int, root_span_key:string}  $context
     */
    public function putInvocation(string $invocationId, array $context): void
    {
        $this->invocations[$invocationId] = $context;
        $this->latestInvocationId = $invocationId;
    }

    /**
     * @return array{trace_id:int, root_span_id:int, root_span_key:string}|null
     */
    public function getInvocation(string $invocationId): ?array
    {
        return $this->invocations[$invocationId] ?? null;
    }

    public function forgetInvocation(string $invocationId): void
    {
        unset($this->invocations[$invocationId]);

        if ($this->latestInvocationId === $invocationId) {
            $this->latestInvocationId = array_key_last($this->invocations);
        }
    }

    public function latestInvocationId(): ?string
    {
        return $this->latestInvocationId;
    }

    /**
     * @param  array{trace_id:int, root_span_id:int, root_span_key:string}  $context
     */
    public function putOperation(string $invocationId, string $operation, array $context): void
    {
        $this->operations[$this->operationKey($invocationId, $operation)] = $context;
    }

    /**
     * @return array{trace_id:int, root_span_id:int, root_span_key:string}|null
     */
    public function getOperation(string $invocationId, string $operation): ?array
    {
        return $this->operations[$this->operationKey($invocationId, $operation)] ?? null;
    }

    public function forgetOperation(string $invocationId, string $operation): void
    {
        unset($this->operations[$this->operationKey($invocationId, $operation)]);
    }

    public function nextFailoverAttempt(string $invocationId): int
    {
        $next = ($this->failoverAttempts[$invocationId] ?? 0) + 1;
        $this->failoverAttempts[$invocationId] = $next;

        return $next;
    }

    public function putTool(string $toolInvocationId, int $spanId): void
    {
        $this->tools[$toolInvocationId] = $spanId;
    }

    public function getTool(string $toolInvocationId): ?int
    {
        return $this->tools[$toolInvocationId] ?? null;
    }

    public function forgetTool(string $toolInvocationId): void
    {
        unset($this->tools[$toolInvocationId]);
    }

    public function flush(): void
    {
        $this->invocations = [];
        $this->tools = [];
        $this->operations = [];
        $this->failoverAttempts = [];
        $this->latestInvocationId = null;
    }

    protected function operationKey(string $invocationId, string $operation): string
    {
        return $invocationId.'::'.$operation;
    }
}
