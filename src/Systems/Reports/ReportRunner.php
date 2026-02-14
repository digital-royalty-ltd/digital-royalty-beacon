<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScope;

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

        Services::logger()->info(LogScope::REPORTS, 'runner_start', 'Report runner started.', [
            'type' => $type,
            'version' => $version,
        ]);

        $generator = $this->registry->find($type, $version);
        if ($generator === null) {
            $msg = 'Unknown report type/version.';
            $this->reports->markFailed($type, $version, $msg);

            Services::logger()->error(LogScope::REPORTS, 'runner_unknown_report', $msg, [
                'type' => $type,
                'version' => $version,
            ]);

            return;
        }

        try {
            $data = $generator->generate();
            $payloadJson = wp_json_encode($data);

            if (!is_string($payloadJson)) {
                $msg = 'Failed to encode report JSON.';
                $this->reports->markFailed($type, $version, $msg);

                Services::logger()->error(LogScope::REPORTS, 'runner_json_encode_failed', $msg, [
                    'type' => $type,
                    'version' => $version,
                ]);

                return;
            }

            $hash = hash('sha256', $payloadJson);
            $generatedAt = current_time('mysql');

            $this->reports->upsertGenerated($type, $version, $payloadJson, $hash, $generatedAt);

            Services::logger()->info(LogScope::REPORTS, 'runner_generated', 'Report generated and stored.', [
                'type' => $type,
                'version' => $version,
                'hash' => $hash,
                'generated_at' => $generatedAt,
                'bytes' => strlen($payloadJson),
            ]);

            $envelope = [
                'type' => $type,
                'version' => $version,
                'generated_at' => $generatedAt,
                'plugin_version' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : 'unknown',
                'data' => $data,
            ];

            Services::logger()->info(LogScope::REPORTS, 'runner_submit_attempt', 'Submitting report envelope.', [
                'type' => $type,
                'version' => $version,
            ]);

            $result = $this->submitter->submit($envelope);

            if (!($result['ok'] ?? false)) {
                $code = (int) ($result['status_code'] ?? 0);
                $err = (string) ($result['error'] ?? 'Unknown submission error.');
                $msg = "Submit failed ({$code}): {$err}";

                $this->reports->markFailed($type, $version, $msg);

                Services::logger()->error(LogScope::REPORTS, 'runner_submit_failed', $msg, [
                    'type' => $type,
                    'version' => $version,
                    'status_code' => $code,
                ]);

                return;
            }

            $submittedAt = current_time('mysql');
            $this->reports->markSubmitted($type, $version, $submittedAt);

            Services::logger()->info(LogScope::REPORTS, 'runner_submit_success', 'Report submitted successfully.', [
                'type' => $type,
                'version' => $version,
                'submitted_at' => $submittedAt,
            ]);
        } catch (\Throwable $e) {
            $this->reports->markFailed($type, $version, $e->getMessage());

            Services::logger()->error(LogScope::REPORTS, 'runner_exception', $e->getMessage(), [
                'type' => $type,
                'version' => $version,
                'exception' => get_class($e),
            ]);
        }
    }
}
