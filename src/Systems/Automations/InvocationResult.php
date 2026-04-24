<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

/**
 * Uniform result shape from every automation invocation.
 *
 * Whether the automation ran synchronously or queued a deferred job,
 * whether it was triggered by a user, an agent, or a scheduler — the
 * caller gets back this consistent structure.
 *
 * Cost reporting contract:
 *   When an automation invocation has incurred a credit cost (by calling
 *   Laravel tool endpoints that debited the client's balance), the
 *   automation should sum those costs and populate:
 *
 *     $data['credits'] = <total credits spent during this invoke()>
 *
 *   The Laravel side reads `data.credits` on the /complete response and
 *   attributes the amount to the channel's monthly_work_spent (without
 *   re-debiting — the debit already happened on the tool side). If no
 *   credits are reported, nothing is attributed to the channel.
 */
final class InvocationResult
{
    public const STATUS_QUEUED    = 'queued';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $status,
        public readonly ?string $deferredId = null,
        public readonly ?string $message = null,
        public readonly array $data = [],
        public readonly ?string $errorCode = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function queued(?string $deferredId = null, ?string $message = null, array $data = []): self
    {
        return new self(
            ok: true,
            status: self::STATUS_QUEUED,
            deferredId: $deferredId,
            message: $message,
            data: $data,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function completed(?string $message = null, array $data = []): self
    {
        return new self(
            ok: true,
            status: self::STATUS_COMPLETED,
            message: $message,
            data: $data,
        );
    }

    public static function failed(string $message, ?string $errorCode = null): self
    {
        return new self(
            ok: false,
            status: self::STATUS_FAILED,
            message: $message,
            errorCode: $errorCode,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok'          => $this->ok,
            'status'      => $this->status,
            'deferred_id' => $this->deferredId,
            'message'     => $this->message,
            'data'        => $this->data,
            'error_code'  => $this->errorCode,
        ];
    }
}
