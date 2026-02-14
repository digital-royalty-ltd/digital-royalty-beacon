<?php

namespace DigitalRoyalty\Beacon\Admin\Tables;

use DigitalRoyalty\Beacon\Repositories\SchedulerRepository;

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class SchedulerListTable extends \WP_List_Table
{
    /** @var array<int, array<string,mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly SchedulerRepository $repo
    ) {
        parent::__construct([
            'singular' => 'beacon_action',
            'plural' => 'beacon_actions',
            'ajax' => false,
        ]);
    }

    public function prepare_items(): void
    {
        $perPage = (int) $this->get_items_per_page('dr_beacon_actions_per_page', 25);
        $page = (int) $this->get_pagenum();

        $result = $this->repo->paginateBeaconActions($perPage, $page);

        $this->rows = $result['rows'] ?? [];
        $this->items = $this->rows;

        $total = (int) ($result['total'] ?? 0);

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
            'hook' => 'Hook',
            'status' => 'Status',
            'next_run' => 'Next run',
            'claim_id' => 'Claim',
            'attempts' => 'Attempts',
            'args' => 'Args',
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        $value = $item[$column_name] ?? '';

        if ($column_name === 'hook') {
            return '<code>' . esc_html((string) $value) . '</code>';
        }

        if ($column_name === 'args') {
            $raw = is_string($value) ? $value : '';
            if ($raw === '') {
                return '';
            }
            $short = strlen($raw) > 160 ? substr($raw, 0, 160) . '…' : $raw;
            return '<code title="' . esc_attr($raw) . '">' . esc_html($short) . '</code>';
        }

        if ($column_name === 'next_run') {
            return $value !== '' ? '<code>' . esc_html((string) $value) . '</code>' : '';
        }

        return esc_html((string) $value);
    }

    public function no_items(): void
    {
        echo esc_html__('No Beacon scheduled actions found.', 'digital-royalty-beacon');
    }
}
