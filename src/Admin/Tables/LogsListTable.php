<?php

namespace DigitalRoyalty\Beacon\Admin\Tables;

use DigitalRoyalty\Beacon\Repositories\LogsRepository;

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class LogsListTable extends \WP_List_Table
{
    /** @var array<int, array<string,mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly LogsRepository $repo
    ) {
        parent::__construct([
            'singular' => 'beacon_log',
            'plural' => 'beacon_logs',
            'ajax' => false,
        ]);
    }

    public function prepare_items(): void
    {
        $perPage = (int) $this->get_items_per_page('dr_beacon_logs_per_page', 50);
        $page = (int) $this->get_pagenum();

        $result = $this->repo->paginate([
            'per_page' => $perPage,
            'page' => $page,
        ]);

        $this->rows = $result['rows'] ?? [];
        $total = (int) ($result['total'] ?? 0);

        // This is the key line WP_List_Table expects
        $this->items = $this->rows;

        $this->_column_headers = [$this->get_columns(), [], []];

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
        ]);
    }

    public function get_columns(): array
    {
        return [
            'id' => 'ID',
            'created_at' => 'Time',
            'level' => 'Level',
            'scope' => 'Scope',
            'event' => 'Event',
            'message' => 'Message',
            'context' => 'Context',
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        $value = $item[$column_name] ?? '';

        if ($column_name === 'created_at') {
            return '<code>' . esc_html((string) $value) . '</code>';
        }

        if ($column_name === 'scope' || $column_name === 'event') {
            return '<code>' . esc_html((string) $value) . '</code>';
        }

        if ($column_name === 'context') {
            $raw = is_string($value) ? $value : '';
            if ($raw === '') {
                return '';
            }
            $short = strlen($raw) > 160 ? substr($raw, 0, 160) . '…' : $raw;
            return '<code title="' . esc_attr($raw) . '">' . esc_html($short) . '</code>';
        }

        if ($column_name === 'message') {
            $raw = is_string($value) ? $value : '';
            $short = strlen($raw) > 180 ? substr($raw, 0, 180) . '…' : $raw;
            return esc_html($short);
        }

        return esc_html((string) $value);
    }
}
