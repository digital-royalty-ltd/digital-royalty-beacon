<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred;

interface DeferredCompletionHandlerInterface
{
    /**
     * Handle a completed deferred request.
     *
     * @param array<string,mixed> $row  Deferred request DB row
     * @param array<string,mixed> $data Response data stored on markCompleted(int $id, array $result)
     * @return array{ok: bool, message?: string, meta?: array<string,mixed>}
     */
    public function handle(array $row, array $data): array;
}