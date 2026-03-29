<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred\Handlers;

use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionHandlerInterface;

/**
 * Handles completed Gap Analysis deferred requests.
 *
 * On completion, Laravel returns two artifact IDs — one for content
 * recommendations (topics within existing areas) and one for area
 * recommendations (new silos to build). This handler stores only
 * the artifact IDs in the deferred row result. The plugin fetches
 * full artifact data from Laravel in real time when displaying results.
 */
final class GapAnalysisResultHandler implements DeferredCompletionHandlerInterface
{
    /**
     * @param array<string,mixed> $row  The deferred_requests table row.
     * @param array<string,mixed> $data The decoded poll response body.
     * @return array{ok: bool, message?: string, meta?: array<string,mixed>}
     */
    public function handle(array $row, array $data): array
    {
        $outputs = isset($data['outputs']) && is_array($data['outputs']) ? $data['outputs'] : [];

        $contentRecsArtifactId = null;
        $areaRecsArtifactId    = null;

        foreach ($outputs as $output) {
            if (!is_array($output)) {
                continue;
            }

            $artifactType = $output['artifact_type'] ?? null;
            $artifactId   = isset($output['artifact_id']) && is_string($output['artifact_id'])
                ? $output['artifact_id']
                : null;

            if (!$artifactId) {
                continue;
            }

            if ($artifactType === 'content_recs') {
                $contentRecsArtifactId = $artifactId;
            } elseif ($artifactType === 'area_recs') {
                $areaRecsArtifactId = $artifactId;
            }
        }

        if (!$contentRecsArtifactId && !$areaRecsArtifactId) {
            return [
                'ok'      => false,
                'message' => 'Gap analysis returned no artifact IDs.',
            ];
        }

        return [
            'ok'   => true,
            'meta' => [
                'content_recs_artifact_id' => $contentRecsArtifactId,
                'area_recs_artifact_id'    => $areaRecsArtifactId,
            ],
        ];
    }
}
