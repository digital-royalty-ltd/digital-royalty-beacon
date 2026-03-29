<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\PostExpiryEnum;

final class PostExpiryHandler
{
    public function register(): void
    {
        add_action(PostExpiryEnum::CRON_HOOK, [$this, 'runExpiry']);

        if (!wp_next_scheduled(PostExpiryEnum::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', PostExpiryEnum::CRON_HOOK);
        }
    }

    public function runExpiry(): void
    {
        $posts = get_posts([
            'post_type'   => 'any',
            'post_status' => 'publish',
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

        foreach ($posts as $post) {
            wp_update_post(['ID' => $post->ID, 'post_status' => 'draft']);
        }
    }
}
