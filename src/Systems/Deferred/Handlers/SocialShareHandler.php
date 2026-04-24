<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred\Handlers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionHandlerInterface;
use DigitalRoyalty\Beacon\Systems\Social\SocialPlatformRegistry;

/**
 * Handles completed social post generation.
 *
 * Fetches the SocialPosts artifact, then publishes each post to its target
 * platform via the Beacon API (which holds the OAuth tokens and makes the
 * actual platform API call). Platforms without a configured connection are
 * marked as 'not_connected'.
 */
final class SocialShareHandler implements DeferredCompletionHandlerInterface
{
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $data
     * @return array{ok: bool, message?: string, meta?: array<string,mixed>}
     */
    public function handle(array $row, array $data): array
    {
        $outputs = isset($data['outputs']) && is_array($data['outputs']) ? $data['outputs'] : [];
        $first   = $outputs[0] ?? null;

        $artifactId = is_array($first) && isset($first['artifact_id']) && is_string($first['artifact_id'])
            ? $first['artifact_id']
            : null;

        if (!$artifactId) {
            return [
                'ok' => false,
                'message' => 'No output artifact_id returned by poll.',
            ];
        }

        $artifactRes = Services::apiClient()->getArtifact($artifactId);

        if (!$artifactRes->ok) {
            return [
                'ok' => false,
                'message' => $artifactRes->message ?? 'Failed to fetch artifact.',
            ];
        }

        $artifact = is_array($artifactRes->data['artifact'] ?? null) ? $artifactRes->data['artifact'] : [];
        $payload  = is_array($artifact['payload'] ?? null) ? $artifact['payload'] : [];
        $posts    = is_array($payload['posts'] ?? null) ? $payload['posts'] : [];

        if (empty($posts)) {
            return [
                'ok' => false,
                'message' => 'Artifact contained no social posts.',
            ];
        }

        $results = [];

        foreach ($posts as $post) {
            if (!is_array($post) || !is_string($post['platform'] ?? null)) {
                continue;
            }

            $platform = $post['platform'];
            $text     = (string) ($post['text'] ?? '');

            // Append hashtags if present and not already in text.
            $hashtags = is_array($post['hashtags'] ?? null) ? $post['hashtags'] : [];
            if (!empty($hashtags)) {
                $hashtagString = implode(' ', array_map(
                    fn(string $tag): string => str_starts_with($tag, '#') ? $tag : "#{$tag}",
                    $hashtags
                ));
                if (!str_contains($text, $hashtagString)) {
                    $text = trim($text . "\n\n" . $hashtagString);
                }
            }

            if (!SocialPlatformRegistry::isConnected($platform)) {
                $results[] = [
                    'platform' => $platform,
                    'status'   => 'not_connected',
                    'message'  => "Platform '{$platform}' is not connected.",
                ];
                continue;
            }

            $publishResult = Services::apiClient()->publishSocialPost([
                'platform' => $platform,
                'text'     => $text,
            ]);

            $results[] = [
                'platform'    => $platform,
                'status'      => $publishResult->ok ? 'published' : 'failed',
                'message'     => $publishResult->data['message'] ?? $publishResult->message ?? null,
                'platform_id' => $publishResult->data['platform_id'] ?? null,
            ];
        }

        $publishedCount = count(array_filter($results, fn ($r) => $r['status'] === 'published'));
        $totalCount     = count($results);

        return [
            'ok' => true,
            'message' => "{$publishedCount}/{$totalCount} platforms processed.",
            'meta' => [
                'artifact_id'      => (string) $artifactId,
                'platform_results' => $results,
            ],
        ];
    }
}
