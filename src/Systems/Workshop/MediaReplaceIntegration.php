<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;

final class MediaReplaceIntegration
{
    public function register(): void
    {
        add_filter('media_row_actions', [$this, 'addRowAction'], 10, 2);
        add_filter('attachment_fields_to_edit', [$this, 'addEditField'], 10, 2);
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function addRowAction(array $actions, \WP_Post $post): array
    {
        if (!current_user_can('upload_files')) {
            return $actions;
        }

        $url = add_query_arg(
            ['page' => AdminPageEnum::WORKSHOP, 'tool' => 'media-replace', 'attachment_id' => $post->ID],
            admin_url('admin.php')
        );

        $actions['dr_beacon_replace'] = '<a href="' . esc_url($url) . '">' . esc_html__('Replace Media', 'digital-royalty-beacon') . '</a>';

        return $actions;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function addEditField(array $fields, \WP_Post $post): array
    {
        if (!current_user_can('upload_files')) {
            return $fields;
        }

        $url = add_query_arg(
            ['page' => AdminPageEnum::WORKSHOP, 'tool' => 'media-replace', 'attachment_id' => $post->ID],
            admin_url('admin.php')
        );

        $fields['dr_beacon_replace'] = [
            'label' => __('Beacon', 'digital-royalty-beacon'),
            'input' => 'html',
            'html'  => '<a href="' . esc_url($url) . '" class="button button-small">' . esc_html__('Replace Media File', 'digital-royalty-beacon') . '</a>',
        ];

        return $fields;
    }
}
