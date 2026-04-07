<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

final class ClonePostIntegration
{
    private const CLONE_ACTION = 'dr_beacon_clone_post';

    public function register(): void
    {
        add_filter('post_row_actions', [$this, 'addRowAction'], 10, 2);
        add_filter('page_row_actions', [$this, 'addRowAction'], 10, 2);
        add_action('admin_action_' . self::CLONE_ACTION, [$this, 'handleClone']);
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function addRowAction(array $actions, \WP_Post $post): array
    {
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=' . self::CLONE_ACTION . '&post_id=' . $post->ID),
            self::CLONE_ACTION . '_' . $post->ID
        );

        $actions['dr_beacon_clone'] = '<a href="' . esc_url($url) . '" title="' . esc_attr__('Clone this post as a draft', 'digital-royalty-beacon') . '">Clone</a>';

        return $actions;
    }

    public function handleClone(): void
    {
        $postId = (int) ($_GET['post_id'] ?? 0);

        if ($postId <= 0) {
            wp_die(__('Invalid post ID.', 'digital-royalty-beacon'));
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_GET['_wpnonce'] ?? ''))), self::CLONE_ACTION . '_' . $postId)) {
            wp_die(__('Security check failed.', 'digital-royalty-beacon'));
        }

        if (!current_user_can('edit_post', $postId)) {
            wp_die(__('Insufficient permissions.', 'digital-royalty-beacon'));
        }

        $source = get_post($postId);
        if (!$source) {
            wp_die(__('Source post not found.', 'digital-royalty-beacon'));
        }

        $excludedKeys = (array) get_option('dr_beacon_clone_post_settings', []);
        $excludedMeta = array_values(array_filter(array_map('trim', (array) ($excludedKeys['excluded_meta_keys'] ?? []))));

        $newId = wp_insert_post([
            'post_title'   => $source->post_title . ' (Clone)',
            'post_content' => $source->post_content,
            'post_excerpt' => $source->post_excerpt,
            'post_status'  => 'draft',
            'post_type'    => $source->post_type,
            'post_author'  => get_current_user_id(),
        ]);

        if (is_wp_error($newId) || $newId === 0) {
            wp_die(__('Failed to create clone.', 'digital-royalty-beacon'));
        }

        // Copy meta
        $allMeta = get_post_meta($postId);
        foreach ($allMeta as $key => $values) {
            if (in_array($key, $excludedMeta, true) || str_starts_with($key, '_edit_')) continue;
            foreach ($values as $value) {
                add_post_meta($newId, $key, maybe_unserialize($value));
            }
        }

        // Copy taxonomies
        foreach (get_object_taxonomies($source->post_type) as $taxonomy) {
            $terms = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($newId, $terms, $taxonomy);
            }
        }

        wp_safe_redirect(get_edit_post_link($newId, 'raw'));
        exit;
    }
}
