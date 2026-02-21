<?php

namespace DigitalRoyalty\Beacon\Admin\Components\Tables;

use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use WP_List_Table;

if (!class_exists(WP_List_Table::class)) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class DeferredRequestsListTable extends WP_List_Table
{
    public function __construct(private readonly DeferredRequestsRepository $repo)
    {
        parent::__construct([
            'singular' => 'deferred_request',
            'plural' => 'deferred_requests',
            'ajax' => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'id' => 'ID',
            'status' => 'Status',
            'request_key' => 'Request',
            'poll_path' => 'Poll path',
            'external_id' => 'External ID',
            'attempts' => 'Attempts',
            'next_attempt_at' => 'Next attempt (UTC)',
            'last_error' => 'Last error',
            'created_at' => 'Created (UTC)',
            'updated_at' => 'Updated (UTC)',
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'id' => ['id', false],
            'status' => ['status', false],
            'request_key' => ['request_key', false],
            'external_id' => ['external_id', false],
            'attempts' => ['attempts', false],
            'next_attempt_at' => ['next_attempt_at', true],
            'created_at' => ['created_at', false],
            'updated_at' => ['updated_at', false],
        ];
    }

    public function prepare_items(): void
    {
        $perPage = 20;

        $paged = isset($_GET['paged']) ? absint((int) $_GET['paged']) : 1;

        $orderby = isset($_GET['orderby']) ? sanitize_key((string) $_GET['orderby']) : 'next_attempt_at';
        $order = isset($_GET['order']) ? sanitize_key((string) $_GET['order']) : 'DESC';

        $total = $this->repo->countAll();
        $items = $this->repo->list($perPage, $paged, $orderby, $order);

        $this->items = $items;

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
            $this->get_primary_column_name(),
        ];

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        $value = $item[$column_name] ?? '';

        if ($column_name === 'poll_path' || $column_name === 'request_key' || $column_name === 'external_id') {
            return '<code>' . esc_html((string) $value) . '</code>';
        }

        if ($column_name === 'last_error') {
            $v = trim((string) $value);
            if ($v === '') {
                return '<span style="color:#999;">(none)</span>';
            }

            return '<span style="color:#b32d2e;">' . esc_html($this->truncate($v, 160)) . '</span>';
        }

        return esc_html((string) $value);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_status($item): string
    {
        $status = (string) ($item['status'] ?? '');

        $badge = match ($status) {
            'pending' => '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f0f0f1;">pending</span>',
            'completed' => '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#d1e7dd;">completed</span>',
            'failed' => '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f8d7da;">failed</span>',
            default => '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#e2e3e5;">' . esc_html($status) . '</span>',
        };

        return $badge;
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1) . '…';
    }
}