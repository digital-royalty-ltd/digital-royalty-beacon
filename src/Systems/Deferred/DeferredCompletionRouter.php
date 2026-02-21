<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred;

use DigitalRoyalty\Beacon\Support\Enums\Deferred\DeferredRequestKeyEnum;
use InvalidArgumentException;

final class DeferredCompletionRouter
{
    /** @var array<string, DeferredCompletionHandlerInterface> */
    private array $handlers = [];

    /**
     * Register a completion handler for a deferred request key.
     *
     * @param string $requestKey One of DeferredRequestKeyEnum::* constants
     */
    public function register(string $requestKey, DeferredCompletionHandlerInterface $handler): void
    {
        if (!$this->isValidKey($requestKey)) {
            throw new InvalidArgumentException('Unknown deferred request key: ' . $requestKey);
        }

        $this->handlers[$requestKey] = $handler;
    }

    public function resolve(?string $requestKey): ?DeferredCompletionHandlerInterface
    {
        $requestKey = is_string($requestKey) ? trim($requestKey) : '';

        if ($requestKey === '') {
            return null;
        }

        return $this->handlers[$requestKey] ?? null;
    }

    private function isValidKey(string $key): bool
    {
        $consts = (new \ReflectionClass(DeferredRequestKeyEnum::class))->getConstants();

        return in_array($key, $consts, true);
    }
}