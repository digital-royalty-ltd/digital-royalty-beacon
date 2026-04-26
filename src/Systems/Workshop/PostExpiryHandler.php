<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\PostExpiryEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

final class PostExpiryHandler
{
    private const NONCE_ACTION = 'dr_beacon_post_expiry_editor';
    private const NONCE_NAME = '_dr_beacon_post_expiry_nonce';

    public function register(): void
    {
        add_action(PostExpiryEnum::CRON_HOOK, [$this, 'runExpiry']);
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post', [$this, 'saveFromEditor']);

        if (!wp_next_scheduled(PostExpiryEnum::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', PostExpiryEnum::CRON_HOOK);
        }
    }

    public function registerMetaBox(): void
    {
        foreach (get_post_types(['show_ui' => true], 'names') as $postType) {
            add_meta_box(
                'dr-beacon-post-expiry',
                __('Beacon Post Expiry', 'digital-royalty-beacon'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'default'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $expireAt = (string) get_post_meta($post->ID, PostExpiryEnum::META_KEY, true);
        $action = (string) get_post_meta($post->ID, PostExpiryEnum::META_ACTION_KEY, true);
        $settings = (array) get_option(PostExpiryEnum::OPTION_SETTINGS, []);
        $notifyEmail = (string) ($settings['notify_email'] ?? '');

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <p>
            <label for="dr-beacon-expire-at"><?php esc_html_e('Expiry time', 'digital-royalty-beacon'); ?></label><br>
            <input
                id="dr-beacon-expire-at"
                name="dr_beacon_expire_at"
                type="datetime-local"
                value="<?php echo esc_attr($expireAt !== '' ? str_replace(' ', 'T', substr($expireAt, 0, 16)) : ''); ?>"
                style="width:100%;"
            >
        </p>
        <p>
            <label for="dr-beacon-expire-action"><?php esc_html_e('Expiry action', 'digital-royalty-beacon'); ?></label><br>
            <select id="dr-beacon-expire-action" name="dr_beacon_expire_action" style="width:100%;">
                <option value="draft" <?php selected($action ?: 'draft', 'draft'); ?>><?php esc_html_e('Move to draft', 'digital-royalty-beacon'); ?></option>
                <option value="private" <?php selected($action, 'private'); ?>><?php esc_html_e('Make private', 'digital-royalty-beacon'); ?></option>
                <option value="trash" <?php selected($action, 'trash'); ?>><?php esc_html_e('Move to trash', 'digital-royalty-beacon'); ?></option>
            </select>
        </p>
        <p>
            <label for="dr-beacon-notify-email"><?php esc_html_e('Notify email', 'digital-royalty-beacon'); ?></label><br>
            <input
                id="dr-beacon-notify-email"
                name="dr_beacon_notify_email"
                type="email"
                value="<?php echo esc_attr($notifyEmail); ?>"
                style="width:100%;"
            >
        </p>
        <?php
    }

    public function saveFromEditor(int $postId): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $expireAt = sanitize_text_field((string) ($_POST['dr_beacon_expire_at'] ?? ''));
        $action = sanitize_key((string) ($_POST['dr_beacon_expire_action'] ?? 'draft'));
        $notifyEmail = sanitize_email((string) ($_POST['dr_beacon_notify_email'] ?? ''));

        if ($expireAt === '') {
            delete_post_meta($postId, PostExpiryEnum::META_KEY);
            delete_post_meta($postId, PostExpiryEnum::META_ACTION_KEY);
        } else {
            update_post_meta($postId, PostExpiryEnum::META_KEY, str_replace('T', ' ', $expireAt) . ':00');
            update_post_meta($postId, PostExpiryEnum::META_ACTION_KEY, in_array($action, ['draft', 'private', 'trash'], true) ? $action : 'draft');
        }

        update_option(PostExpiryEnum::OPTION_SETTINGS, [
            'notify_email' => $notifyEmail,
        ], false);
    }

    public function runExpiry(): void
    {
        $posts = get_posts([
            'post_type'   => 'any',
            'post_status' => ['publish', 'private', 'draft'],
            'numberposts' => -1,
            'meta_query'  => [
                [
                    'key'     => PostExpiryEnum::META_KEY,
                    'value'   => current_time('mysql'),
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ],
            ],
        ]);

        if ($posts === []) {
            return;
        }

        $logger = Services::logger();
        $applied = [];
        $failed = [];

        foreach ($posts as $post) {
            $postId = (int) $post->ID;
            $action = (string) get_post_meta($postId, PostExpiryEnum::META_ACTION_KEY, true);
            $action = in_array($action, ['draft', 'private', 'trash'], true) ? $action : 'draft';

            $ok = false;
            $errorMessage = null;

            if ($action === 'trash') {
                $ok = wp_trash_post($postId) !== false;
            } else {
                $result = wp_update_post(['ID' => $postId, 'post_status' => $action], true);
                if ($result instanceof \WP_Error) {
                    $errorMessage = $result->get_error_message();
                } else {
                    $ok = (int) $result > 0;
                }
            }

            if ($ok) {
                $applied[] = ['post_id' => $postId, 'action' => $action, 'title' => get_the_title($post)];
                delete_post_meta($postId, PostExpiryEnum::META_KEY);
                delete_post_meta($postId, PostExpiryEnum::META_ACTION_KEY);
            } else {
                // Don't strip the meta on failure — without this, an expiry
                // that failed would never retry on the next cron run.
                $failed[] = ['post_id' => $postId, 'action' => $action, 'error' => $errorMessage];
                try {
                    $logger->warning(
                        LogScopeEnum::BACKGROUND,
                        'post_expiry_action_failed',
                        "Could not apply expiry action '{$action}' to post #{$postId}.",
                        ['post_id' => $postId, 'action' => $action, 'error' => $errorMessage]
                    );
                } catch (\Throwable) {
                    // ignore
                }
                continue;
            }

            $settings = (array) get_option(PostExpiryEnum::OPTION_SETTINGS, []);
            $notifyTo = sanitize_email((string) ($settings['notify_email'] ?? ''));

            if ($notifyTo !== '' && is_email($notifyTo)) {
                wp_mail(
                    $notifyTo,
                    sprintf('Beacon expiry ran for "%s"', get_the_title($post)),
                    sprintf(
                        "Beacon changed post #%d (%s) to %s after its scheduled expiry time.",
                        $postId,
                        get_the_title($post),
                        $action
                    )
                );
            }
        }

        try {
            $logger->info(
                LogScopeEnum::BACKGROUND,
                'post_expiry_run_complete',
                sprintf('Post expiry run complete: applied=%d, failed=%d.', count($applied), count($failed)),
                [
                    'applied_count' => count($applied),
                    'failed_count' => count($failed),
                    'applied' => $applied,
                ]
            );
        } catch (\Throwable) {
            // ignore
        }
    }
}
