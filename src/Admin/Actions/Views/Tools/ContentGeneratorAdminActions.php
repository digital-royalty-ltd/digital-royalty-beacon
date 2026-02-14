<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Views\Tools;

use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use WP_Error;
use WP_Taxonomy;

final class ContentGeneratorAdminActions
{
    public const ACTION = 'dr_beacon_generate_content';

    public function __construct(private readonly ApiClient $api) {}

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
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dr_beacon_nonce'])), self::ACTION)
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

        $taxInputRaw = $_POST['tax_input'] ?? [];
        if (!is_array($taxInputRaw)) {
            $taxInputRaw = [];
        }

        $allowedTaxonomies = get_object_taxonomies($postType, 'names');

        $sanitizedTaxInput = [];
        foreach ($taxInputRaw as $taxonomy => $value) {
            $taxonomy = sanitize_key($taxonomy);
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
                $termIds = array_values(array_filter($termIds, static fn($id) => $id > 0));
                $sanitizedTaxInput[$taxonomy] = $termIds;
            } else {
                $val = sanitize_text_field(wp_unslash((string) $value));
                $sanitizedTaxInput[$taxonomy] = $val;
            }
        }

        $response = $this->api->generateContent([
            'post_type' => $postType,
            'prompt' => $prompt,
            'tax_input' => $sanitizedTaxInput,
        ]);

        if (is_wp_error($response)) {
            wp_die($response->get_error_message());
        }

        $postId = wp_insert_post([
            'post_type'    => $postType,
            'post_status'  => 'draft',
            'post_title'   => $response['title'] ?? 'Generated Draft',
            'post_content' => $response['content'] ?? '',
        ], true);

        if ($postId instanceof WP_Error) {
            wp_die($postId->get_error_message());
        }

        $this->applyTaxonomies((int) $postId, $postType, $sanitizedTaxInput);

        wp_safe_redirect(admin_url('post.php?post=' . absint($postId) . '&action=edit'));
        exit;
    }

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
                $termIds = array_values(array_filter($termIds, static fn($id) => $id > 0));
                if ($termIds) {
                    wp_set_object_terms($postId, $termIds, $taxonomy, false);
                }
            } else {
                $names = array_filter(array_map('trim', explode(',', (string) $value)));
                if ($names) {
                    wp_set_object_terms($postId, $names, $taxonomy, false);
                }
            }
        }
    }
}
