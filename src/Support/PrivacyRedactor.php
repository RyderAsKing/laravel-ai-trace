<?php

namespace RyderAsKing\LaravelAiTrace\Support;

class PrivacyRedactor
{
    public function sanitizeText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return match ((string) config('ai-trace.record_content_mode', 'redacted')) {
            'none' => '[hidden]',
            'hash' => sha1($value),
            'full' => $this->applyCallback($value),
            default => $this->applyCallback($this->redact($value)),
        };
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $mode = (string) config('ai-trace.record_content_mode', 'redacted');

        if ($mode === 'none') {
            return '[hidden]';
        }

        if (is_string($value)) {
            return $this->sanitizeText($value);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->sanitizeValue($item), $value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $this->sanitizeValue($value->toArray());
            }

            if ($value instanceof \JsonSerializable) {
                return $this->sanitizeValue($value->jsonSerialize());
            }

            return $this->sanitizeValue((array) $value);
        }

        if ($mode === 'hash') {
            return sha1((string) $value);
        }

        return $value;
    }

    protected function applyCallback(string $value): string
    {
        $callback = config('ai-trace.redaction.callback');

        if (! is_callable($callback)) {
            return $value;
        }

        $result = $callback($value);

        return is_string($result) ? $result : $value;
    }

    protected function redact(string $text): string
    {
        $redacted = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $text) ?? $text;
        $redacted = preg_replace('/(sk|pk|rk|tok|key)_[A-Za-z0-9\-_]{8,}/i', '[redacted-token]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\+?[0-9][0-9\-\s]{7,}[0-9]/', '[redacted-phone]', $redacted) ?? $redacted;

        return $redacted;
    }
}
