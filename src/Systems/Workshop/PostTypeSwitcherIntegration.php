<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

final class PostTypeSwitcherIntegration
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_filter('bulk_actions-edit-post', [$this, 'addBulkAction']);
        add_filter('bulk_actions-edit-page', [$this, 'addBulkAction']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handleBulkAction'], 10, 3);
        add_filter('handle_bulk_actions-edit-page', [$this, 'handleBulkAction'], 10, 3);
        add_action('admin_notices', [$this, 'showBulkNotice']);
    }

    public function registerMetaBox(): void
    {
        foreach (get_post_types(['show_ui' => true], 'names') as $postType) {
            add_meta_box(
                'dr-beacon-post-type-switcher',
                __('Switch Post Type', 'digital-royalty-beacon'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'low'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $types = get_post_types(['show_ui' => true], 'objects');
        wp_nonce_field('dr_beacon_switch_type', '_dr_beacon_switch_type_nonce');
        ?>
        <select name="dr_beacon_switch_to_type" style="width:100%;">
            <option value="">— Keep current (<?php echo esc_html($post->post_type); ?>)</option>
            <?php foreach ($types as $type): ?>
                <?php if ($type->name !== $post->post_type): ?>
                    <option value="<?php echo esc_attr($type->name); ?>"><?php echo esc_html($type->labels->singular_name); ?> (<?php echo esc_attr($type->name); ?>)</option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <p class="description" style="margin-top:6px;"><?php esc_html_e('Changing the post type happens on save.', 'digital-royalty-beacon'); ?></p>
        <?php

        // Hook into save_post to handle the switch
        add_action('save_post', function (int $postId) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (!isset($_POST['_dr_beacon_switch_type_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['_dr_beacon_switch_type_nonce'])), 'dr_beacon_switch_type')) return;
            if (!current_user_can('edit_post', $postId)) return;

            $newType = sanitize_key((string) ($_POST['dr_beacon_switch_to_type'] ?? ''));
            if ($newType === '' || $newType === get_post_type($postId)) return;
            if (!post_type_exists($newType)) return;

            // Use direct query to avoid infinite save_post loops
            global $wpdb;
            $wpdb->update($wpdb->posts, ['post_type' => $newType], ['ID' => $postId], ['%s'], ['%d']);
            clean_post_cache($postId);
        });
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function addBulkAction(array $actions): array
    {
        $types = get_post_types(['show_ui' => true], 'objects');
        foreach ($types as $type) {
            $actions['dr_beacon_switch_to_' . $type->name] = 'Switch to ' . $type->labels->singular_name;
        }
        return $actions;
    }

    /**
     * @param int[] $postIds
     */
    public function handleBulkAction(string $redirectTo, string $action, array $postIds): string
    {
        if (strpos($action, 'dr_beacon_switch_to_') !== 0) return $redirectTo;

        $newType = substr($action, strlen('dr_beacon_switch_to_'));
        if (!post_type_exists($newType)) return $redirectTo;

        global $wpdb;
        $count = 0;
        foreach ($postIds as $id) {
            if (!current_user_can('edit_post', (int) $id)) continue;
            $wpdb->update($wpdb->posts, ['post_type' => $newType], ['ID' => (int) $id], ['%s'], ['%d']);
            clean_post_cache((int) $id);
            $count++;
        }

        return add_query_arg('dr_beacon_switched', $count, $redirectTo);
    }

    public function showBulkNotice(): void
    {
        if (!isset($_GET['dr_beacon_switched'])) return;
        $count = (int) $_GET['dr_beacon_switched'];
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(_n('%d post type changed.', '%d post types changed.', $count, 'digital-royalty-beacon'), $count))
        );
    }
}
