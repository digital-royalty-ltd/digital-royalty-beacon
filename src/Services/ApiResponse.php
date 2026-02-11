<?php

namespace DigitalRoyalty\Beacon\Services;

final class ApiResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $code,
        public readonly ?string $message = null,
        /** @var array<string,mixed> */
        public readonly array $data = []
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