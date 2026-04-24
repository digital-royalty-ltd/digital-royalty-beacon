<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

/**
 * Identifies who triggered an automation invocation.
 *
 * Used for audit logging and billing attribution. Format:
 *   - "user:{id}"           — a WP admin clicking a button
 *   - "agent:{agent_key}"   — a campaign AI agent
 *   - "scheduler"           — a recurring schedule
 *   - "api"                 — an external API consumer
 */
final class InvocationActor
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $identifier = null,
    ) {}

    public static function user(int $userId): self
    {
        return new self('user', (string) $userId);
    }

    public static function agent(string $agentKey): self
    {
        return new self('agent', $agentKey);
    }

    public static function scheduler(): self
    {
        return new self('scheduler');
    }

    public static function api(): self
    {
        return new self('api');
    }

    public function toString(): string
    {
        return $this->identifier !== null
            ? "{$this->type}:{$this->identifier}"
            : $this->type;
    }
}
