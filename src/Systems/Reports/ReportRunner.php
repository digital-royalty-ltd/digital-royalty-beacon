<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Systems\Reports\ReportRegistry;

final class ReportRunner
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportsRepository $reports,
        private readonly ReportSubmitter $submitter
    ) {}

    public function run(string $type, int $version): void
    {
        update_option('dr_beacon_last_runner_heartbeat', current_time('mysql'), false);

        $generator = $this->registry->find($type, $version);
        if ($generator === null) {
            $this->reports->markFailed($type, $version, 'Unknown report type/version.');
            return;
        }

        try {
            $data = $generator->generate();
            $payloadJson = wp_json_encode($data);

            if (!is_string($payloadJson)) {
                $this->reports->markFailed($type, $version, 'Failed to encode report JSON.');
                return;
            }

            $hash = hash('sha256', $payloadJson);
            $generatedAt = current_time('mysql');

            $this->reports->upsertGenerated($type, $version, $payloadJson, $hash, $generatedAt);

            $envelope = [
                'type' => $type,
                'version' => $version,
                'generated_at' => $generatedAt,
                'plugin_version' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : 'unknown',
                'data' => $data,
            ];

            $result = $this->submitter->submit($envelope);

            if (!($result['ok'] ?? false)) {
                $code = (int) ($result['status_code'] ?? 0);
                $err = (string) ($result['error'] ?? 'Unknown submission error.');
                $this->reports->markFailed($type, $version, "Submit failed ({$code}): {$err}");
                return;
            }

            $this->reports->markSubmitted($type, $version, current_time('mysql'));
        } catch (\Throwable $e) {
            $this->reports->markFailed($type, $version, $e->getMessage());
        }
    }
}