<?php

namespace DigitalRoyalty\Beacon\Systems\Api;

final class ApiResponse
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        public readonly bool $ok,
        public readonly int $code,
        public readonly ?string $message,
        public readonly array $data,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $location = null,
        public readonly ?int $deferredRequestId = null
    ) {}

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isUnauthorized(): bool
    {
        return $this->code === 401 || $this->code === 403;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}