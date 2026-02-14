<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScope;

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

        $envelopeId = (string) $envelope['envelope_id'];
        $reportType = (string) ($envelope['report_type'] ?? $envelope['type'] ?? '');
        $reportVersion = isset($envelope['report_version'])
            ? (string) $envelope['report_version']
            : (isset($envelope['version']) ? (string) $envelope['version'] : null);

        Services::logger()->info(LogScope::REPORTS, 'submitter_prepare', 'Preparing report submission payload.', [
            'envelope_id' => $envelopeId,
            'report_type' => $reportType,
            'report_version' => $reportVersion,
        ]);

        // Normalize payload to API contract
        $payload = [
            'envelope_id'    => $envelopeId,
            'report_type'    => $reportType,
            'report_version' => $reportVersion,
            'payload'        => $envelope['payload'] ?? $envelope['data'] ?? null,
        ];

        if ($payload['report_type'] === '' || $payload['payload'] === null) {
            Services::logger()->warning(LogScope::REPORTS, 'submitter_invalid_payload', 'Report submission payload missing required fields.', [
                'envelope_id' => $envelopeId,
                'report_type' => $payload['report_type'],
                'has_payload' => $payload['payload'] !== null,
            ]);
        }

        $client = Services::apiClient();

        Services::logger()->info(LogScope::REPORTS, 'submitter_request', 'Submitting report to API.', [
            'envelope_id' => $envelopeId,
            'report_type' => $reportType,
            'report_version' => $reportVersion,
        ]);

        $response = $client->submitReports($payload);

        if (!$response->isOk()) {
            $error = $response->message ?? 'Report submission failed.';

            Services::logger()->error(LogScope::REPORTS, 'submitter_failed', $error, [
                'envelope_id' => $envelopeId,
                'report_type' => $reportType,
                'report_version' => $reportVersion,
                'status_code' => $response->code,
                'data' => $response->data,
            ]);

            return [
                'ok' => false,
                'status_code' => $response->code,
                'error' => $error,
            ];
        }

        Services::logger()->info(LogScope::REPORTS, 'submitter_success', 'Report submitted successfully.', [
            'envelope_id' => $envelopeId,
            'report_type' => $reportType,
            'report_version' => $reportVersion,
            'status_code' => $response->code,
            'report_id' => (string) $response->get('report_id', ''),
        ]);

        return [
            'ok' => true,
            'status_code' => $response->code,
            'error' => null,
        ];
    }
}
