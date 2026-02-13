<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Services\Services;

final class ReportSubmitter
{
    /**
     * @param array<string, mixed> $envelope
     * @return array{ok: bool, status_code: int, error: string|null}
     */
    public function submit(array $envelope): array
    {
        // Idempotency key
        if (empty($envelope['envelope_id'])) {
            $envelope['envelope_id'] = wp_generate_uuid4();
        }

        // Normalize payload to API contract
        $payload = [
            'envelope_id'    => (string) $envelope['envelope_id'],
            'report_type'    => (string) ($envelope['report_type'] ?? $envelope['type'] ?? ''),
            'report_version' => isset($envelope['report_version'])
                ? (string) $envelope['report_version']
                : (isset($envelope['version']) ? (string) $envelope['version'] : null),
            'payload'        => $envelope['payload'] ?? $envelope['data'] ?? null,
        ];

        $client = Services::apiClient();
        $response = $client->submitReports($payload);

        if (!$response->isOk()) {
            return [
                'ok' => false,
                'status_code' => $response->code,
                'error' => $response->message ?? 'Report submission failed.',
            ];
        }

        return [
            'ok' => true,
            'status_code' => $response->code,
            'error' => null,
        ];
    }
}
