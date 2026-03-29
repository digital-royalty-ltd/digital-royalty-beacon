<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationTypeEnum;
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
                'submitted_at' => $this->safeString($row['submitted_at'] ?? null),
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
        $automation = $this->registry->find($automationKey);

        if (!$automation) {
            return ['ok' => false, 'message' => 'Automation not found.'];
        }

        if (!$automation->deferredKey()) {
            return ['ok' => false, 'message' => 'This automation cannot be triggered programmatically.'];
        }

        $depCheck = $this->checkDependencies($automation);

        if (!$depCheck['met']) {
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
            return ['ok' => false, 'message' => 'No API handler registered for this automation.'];
        }

        if (!$apiResponse->ok) {
            return ['ok' => false, 'message' => $apiResponse->message ?? 'API request failed.'];
        }

        $result = ['ok' => true, 'message' => 'Automation queued.'];

        if ($apiResponse->deferredRequestId) {
            $result['deferred_request_id'] = $apiResponse->deferredRequestId;
        }

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
        if (!$row || ($row['status'] ?? '') !== 'submitted') {
            return 'missing';
        }

        if ($maxAgeDays === null) {
            return 'ok';
        }

        $submittedAt = $row['submitted_at'] ?? null;

        if (!is_string($submittedAt) || trim($submittedAt) === '') {
            return 'missing';
        }

        $ts = strtotime($submittedAt . ' UTC');

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
