<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Views\Tools;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use WP_Error;
use WP_Taxonomy;

final class ContentGeneratorAdminActions
{
    public const ACTION = 'dr_beacon_generate_content';
    private const TOOL_SLUG = 'content-generator';


    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied.', 'digital-royalty'));
        }

        if (
            !isset($_POST['dr_beacon_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['dr_beacon_nonce'])),
                self::ACTION
            )
        ) {
            wp_die(__('Security check failed.', 'digital-royalty'));
        }

        $postType = sanitize_text_field(wp_unslash($_POST['post_type'] ?? 'post'));
        if (!post_type_exists($postType)) {
            wp_die(__('Invalid post type.', 'digital-royalty'));
        }

        $prompt = '';
        if (isset($_POST['prompt'])) {
            $prompt = sanitize_textarea_field(wp_unslash($_POST['prompt']));
        }

        // Tax inputs (same structure WP uses)
        $taxInputRaw = $_POST['tax_input'] ?? [];
        if (!is_array($taxInputRaw)) {
            $taxInputRaw = [];
        }

        $allowedTaxonomies = get_object_taxonomies($postType, 'names');

        $sanitizedTaxInput = [];
        foreach ($taxInputRaw as $taxonomy => $value) {
            $taxonomy = sanitize_key((string) $taxonomy);

            if (!in_array($taxonomy, $allowedTaxonomies, true)) {
                continue;
            }

            $taxObj = get_taxonomy($taxonomy);
            if (!$taxObj instanceof WP_Taxonomy) {
                continue;
            }

            if ($taxObj->hierarchical) {
                if (!is_array($value)) {
                    continue;
                }

                $termIds = array_map('absint', $value);
                $termIds = array_values(array_filter($termIds, static fn ($id) => $id > 0));

                $sanitizedTaxInput[$taxonomy] = $termIds;
                continue;
            }

            // Non-hierarchical taxonomies, core meta box submits a comma-separated string
            if (is_array($value)) {
                $value = implode(',', array_map('sanitize_text_field', array_map('wp_unslash', $value)));
            } else {
                $value = sanitize_text_field(wp_unslash((string) $value));
            }

            $sanitizedTaxInput[$taxonomy] = $value;
        }

        $payload = [
            'post_type' => $postType,
            'prompt' => $prompt !== '' ? $prompt : null,
            'tax_input' => $sanitizedTaxInput,
        ];

        $api = Services::apiClient();
        // This will auto-enqueue if the API returns 202 (inside ApiClient::request()).
        $response = $api->generateContentDraft($payload);

        if ($response->code === 202) {
            $delay = $response->retryAfterSeconds ?? 15;

            $msg = 'Generation queued. Beacon will retry automatically.';
            $msg .= ' Next check: ~' . (int) $delay . 's.';

            if ($response->deferredRequestId) {
                $msg .= ' Queue ID: ' . (int) $response->deferredRequestId . '.';
            }

            $this->redirectBack(true, $msg, $postType);
        }

        if (!$response->ok) {
            $this->redirectBack(false, $response->message ?? 'Beacon API request failed.', $postType);
        }

        $data = $response->data;

        $title = isset($data['title']) && is_string($data['title']) && $data['title'] !== ''
            ? $data['title']
            : 'Generated Draft';

        $content = isset($data['content']) && is_string($data['content'])
            ? $data['content']
            : '';

        $postId = wp_insert_post([
            'post_type' => $postType,
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => $content,
        ], true);

        if ($postId instanceof WP_Error) {
            wp_die($postId->get_error_message());
        }

        $this->applyTaxonomies((int) $postId, $postType, $sanitizedTaxInput);

        wp_safe_redirect(admin_url('post.php?post=' . absint((int) $postId) . '&action=edit'));
        exit;
    }

    private function redirectBack(bool $ok, string $message, string $postType): void
    {
        $url = add_query_arg(
            [
                'page' => AdminPageEnum::TOOLS,
                'tool' => self::TOOL_SLUG,
                'dr_beacon_post_type' => $postType,
                'dr_beacon_ok' => $ok ? '1' : '0',
                'dr_beacon_msg' => rawurlencode($message),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    /**
     * @param array<string,mixed> $taxInput
     */
    private function applyTaxonomies(int $postId, string $postType, array $taxInput): void
    {
        $allowedTaxonomies = get_object_taxonomies($postType, 'names');

        foreach ($taxInput as $taxonomy => $value) {
            if (!in_array($taxonomy, $allowedTaxonomies, true)) {
                continue;
            }

            $taxObj = get_taxonomy($taxonomy);
            if (!$taxObj instanceof WP_Taxonomy) {
                continue;
            }

            if ($taxObj->hierarchical) {
                $termIds = is_array($value) ? array_map('absint', $value) : [];
                $termIds = array_values(array_filter($termIds, static fn ($id) => $id > 0));

                if ($termIds) {
                    wp_set_object_terms($postId, $termIds, $taxonomy, false);
                }

                continue;
            }

            $names = array_filter(array_map('trim', explode(',', (string) $value)));
            if ($names) {
                wp_set_object_terms($postId, $names, $taxonomy, false);
            }
        }
    }
}
