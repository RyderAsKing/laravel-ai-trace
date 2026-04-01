<?php

namespace RyderAsKing\LaravelAiTrace\Support;

class SdkDeduplicator
{
    /**
     * @var array<string, int>
     */
    protected array $seenUntil = [];

    public function isDuplicate(string $key, int $ttlSeconds): bool
    {
        $now = time();
        $this->prune($now);

        if (($this->seenUntil[$key] ?? 0) >= $now) {
            return true;
        }

        $this->seenUntil[$key] = $now + max(1, $ttlSeconds);

        return false;
    }

    public function flush(): void
    {
        $this->seenUntil = [];
    }

    protected function prune(int $now): void
    {
        foreach ($this->seenUntil as $key => $until) {
            if ($until < $now) {
                unset($this->seenUntil[$key]);
            }
        }
    }
}
