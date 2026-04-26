<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationTypeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;

final class AutomationManager
{
    public function __construct(
        private readonly AutomationRegistry        $registry,
        private readonly ReportsRepository         $reportsRepo,
        private readonly DeferredRequestsRepository $deferredRepo
    ) {}

    /**
     * Check dependency status for an automation.
     *
     * @return array{
     *   met: bool,
     *   items: array<int, array{
     *     report_type: string,
     *     label: string,
     *     max_age_days: int|null,
     *     status: 'ok'|'missing'|'stale',
     *     submitted_at: string|null
     *   }>
     * }
     */
    public function checkDependencies(AutomationInterface $automation): array
    {
        $items = [];
        $allMet = true;

        foreach ($automation->dependencies() as $dep) {
            $row    = $this->reportsRepo->getLatestByType($dep->reportType);
            $status = $this->resolveDependencyStatus($row, $dep->maxAgeDays);

            if ($status !== 'ok') {
                $allMet = false;
            }

            $items[] = [
                'report_type'  => $dep->reportType,
                'label'        => ReportTypeEnum::label($dep->reportType),
                'max_age_days' => $dep->maxAgeDays,
                'status'       => $status,
                'submitted_at' => $this->safeString($row['submitted_at'] ?? $row['generated_at'] ?? null),
            ];
        }

        return ['met' => $allMet, 'items' => $items];
    }

    /**
     * Run a background automation by key.
     * Returns a result array indicating success or failure reason.
     *
     * @return array{ok: bool, message: string, deferred_request_id?: int}
     */
    public function run(string $automationKey): array
    {
        $logger = Services::logger();

        $automation = $this->registry->find($automationKey);

        if (!$automation) {
            $logger->warning(
                LogScopeEnum::SYSTEM,
                'automation_run_rejected',
                "Automation run rejected: unknown key '{$automationKey}'.",
                ['automation_key' => $automationKey]
            );
            return ['ok' => false, 'message' => 'Automation not found.'];
        }

        if (!$automation->deferredKey()) {
            $logger->info(
                LogScopeEnum::SYSTEM,
                'automation_run_rejected',
                "Automation '{$automationKey}' has no deferred key — not triggerable programmatically.",
                ['automation_key' => $automationKey]
            );
            return ['ok' => false, 'message' => 'This automation cannot be triggered programmatically.'];
        }

        $depCheck = $this->checkDependencies($automation);

        if (!$depCheck['met']) {
            // Capture which specific deps blocked the run — without this,
            // the operator just sees "dependencies missing or stale" with
            // no clue which report needs running.
            $missingOrStale = array_values(array_filter(
                $depCheck['items'],
                static fn ($item) => ($item['status'] ?? 'ok') !== 'ok'
            ));

            $logger->warning(
                LogScopeEnum::SYSTEM,
                'automation_dependencies_unmet',
                "Automation '{$automationKey}' blocked by report dependencies.",
                [
                    'automation_key' => $automationKey,
                    'unmet' => $missingOrStale,
                ]
            );
            return ['ok' => false, 'message' => 'One or more report dependencies are missing or stale. Run site reports first.'];
        }

        $reports = $this->collectReports($automation);

        $apiClient = Services::apiClient();

        $apiResponse = match ($automationKey) {
            AutomationTypeEnum::GAP_ANALYSIS => $apiClient->runGapAnalysis(
                $this->buildGapAnalysisPayload($reports)
            ),
            default => null,
        };

        if ($apiResponse === null) {
            $logger->warning(
                LogScopeEnum::SYSTEM,
                'automation_run_no_handler',
                "No API handler registered for automation '{$automationKey}'.",
                ['automation_key' => $automationKey]
            );
            return ['ok' => false, 'message' => 'No API handler registered for this automation.'];
        }

        if (!$apiResponse->ok) {
            $logger->warning(
                LogScopeEnum::API,
                'automation_run_api_failed',
                "Automation '{$automationKey}' API request failed.",
                [
                    'automation_key' => $automationKey,
                    'status_code' => $apiResponse->code,
                    'message' => $apiResponse->message,
                ]
            );
            return ['ok' => false, 'message' => $apiResponse->message ?? 'API request failed.'];
        }

        $result = ['ok' => true, 'message' => 'Automation queued.'];

        if ($apiResponse->deferredRequestId) {
            $result['deferred_request_id'] = $apiResponse->deferredRequestId;
        }

        $logger->info(
            LogScopeEnum::SYSTEM,
            'automation_run_queued',
            "Automation '{$automationKey}' queued.",
            [
                'automation_key' => $automationKey,
                'deferred_request_id' => $apiResponse->deferredRequestId,
            ]
        );

        return $result;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string,mixed>|null $row
     */
    private function resolveDependencyStatus(?array $row, ?int $maxAgeDays): string
    {
        $status = $row['status'] ?? '';
        if (!$row || !in_array($status, ['generated', 'submitted'], true)) {
            return 'missing';
        }

        if ($maxAgeDays === null) {
            return 'ok';
        }

        // Use submitted_at if available, otherwise fall back to generated_at.
        $dateField = $row['submitted_at'] ?? $row['generated_at'] ?? null;

        if (!is_string($dateField) || trim($dateField) === '') {
            return 'missing';
        }

        $ts = strtotime($dateField . ' UTC');

        if ($ts === false) {
            return 'missing';
        }

        $ageDays = (time() - $ts) / 86400;

        return $ageDays <= $maxAgeDays ? 'ok' : 'stale';
    }

    /**
     * Collect the latest report payloads for all dependencies.
     *
     * @return array<string, array<string,mixed>> keyed by report type constant
     */
    private function collectReports(AutomationInterface $automation): array
    {
        $reports = [];

        foreach ($automation->dependencies() as $dep) {
            $row = $this->reportsRepo->getLatestByType($dep->reportType);

            if (!$row) {
                continue;
            }

            $payloadJson = $row['payload'] ?? null;
            $payload     = is_string($payloadJson) ? json_decode($payloadJson, true) : null;

            $reports[$dep->reportType] = is_array($payload) ? $payload : [];
        }

        return $reports;
    }

    /**
     * @param array<string, array<string,mixed>> $reports
     * @return array<string, mixed>
     */
    private function buildGapAnalysisPayload(array $reports): array
    {
        return [
            'profile'       => $reports[ReportTypeEnum::WEBSITE_PROFILE]       ?? [],
            'content_areas' => $reports[ReportTypeEnum::WEBSITE_CONTENT_AREAS]  ?? [],
            'sitemap'       => $reports[ReportTypeEnum::WEBSITE_SITEMAP]        ?? [],
        ];
    }

    private function safeString(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
