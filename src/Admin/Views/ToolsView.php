<?php

namespace DigitalRoyalty\Beacon\Admin\Views;

use DigitalRoyalty\Beacon\Admin\Views\Tools\ContentGeneratorView;
use DigitalRoyalty\Beacon\Admin\Views\Tools\GapSuggestionsView;
use DigitalRoyalty\Beacon\Admin\Views\Tools\MetaGeneratorView;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;

final class ToolsView implements ViewInterface
{
    /** @var ViewInterface[] */
    private array $tools;

    public function __construct()
    {
        $this->tools = [
                new ContentGeneratorView(),
                new MetaGeneratorView(),
                new GapSuggestionsView(),
        ];
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?= $this->title() ?></h1>

            <?php $this->renderToolsHome();?>
        </div>
        <?php
    }

    private function renderToolsHome(): void
    {
        ?>
        <div style="max-width:920px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:14px;">
                <?php foreach ($this->tools as $tool) : ?>
                    <?php
                    echo $this->toolCard([
                            'title' => $tool->title(),
                            'description' => $tool->description(),
                            'status' => $tool->isAvailable() ? 'Available' : 'Coming soon',
                            'cta' => $tool->isAvailable() ? 'Open' : 'View',
                            'url' => $this->toolUrl($tool->slug()),
                            'disabled' => !$tool->isAvailable(),
                    ]);
                    ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function renderComingSoon(ViewInterface $tool): void
    {
        $backUrl = $this->toolUrl('');
        ?>
        <p><a href="<?php echo esc_url($backUrl); ?>" class="button">← Back to Tools</a></p>
        <h2><?php echo esc_html($tool->title()); ?></h2>
        <p class="description"><?php echo esc_html($tool->description()); ?></p>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
            <p class="description" style="margin:0;">Coming soon.</p>
        </div>
        <?php
    }

    private function findTool(string $slug): ?ViewInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->slug() === $slug) {
                return $tool;
            }
        }
        return null;
    }

    private function toolUrl(string $tool): string
    {
        $args = ['page' => AdminPageEnum::TOOLS];

        if ($tool !== '') {
            $args['tool'] = $tool;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @param array{
     *   title:string,
     *   description:string,
     *   status:string,
     *   cta:string,
     *   url:string,
     *   disabled?:bool
     * } $tool
     */
    private function toolCard(array $tool): string
    {
        $title = (string) ($tool['title'] ?? '');
        $desc = (string) ($tool['description'] ?? '');
        $status = (string) ($tool['status'] ?? '');
        $cta = (string) ($tool['cta'] ?? 'Open');
        $url = (string) ($tool['url'] ?? '#');
        $disabled = !empty($tool['disabled']);

        $badgeStyle = 'border:1px solid #dcdcde;background:#f6f7f7;color:#646970;';

        $card = '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;display:flex;flex-direction:column;gap:10px;min-height:178px;">';
        $card .= '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">';
        $card .= '<div>';
        $card .= '<h3 style="margin:0 0 6px;font-size:16px;line-height:1.2;">' . esc_html($title) . '</h3>';
        $card .= '<div class="description" style="margin:0;">' . esc_html($desc) . '</div>';
        $card .= '</div>';
        $card .= '<div style="font-size:12px;white-space:nowrap;padding:4px 8px;border-radius:999px;' . esc_attr($badgeStyle) . '">' . esc_html($status) . '</div>';
        $card .= '</div>';

        $card .= '<div style="margin-top:auto;display:flex;justify-content:flex-end;">';

        if ($disabled) {
            $card .= '<span class="button disabled" style="pointer-events:none;opacity:0.65;">' . esc_html($cta) . '</span>';
        } else {
            $card .= '<a href="' . esc_url($url) . '" class="button button-primary" style="text-decoration:none;">' . esc_html($cta) . '</a>';
        }

        $card .= '</div>';
        $card .= '</div>';

        return $card;
    }

    private function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        $len = strlen($key);
        if ($len <= 10) {
            return str_repeat('*', $len);
        }

        $start = substr($key, 0, 6);
        $end = substr($key, -4);

        return $start . str_repeat('*', max(0, $len - 10)) . $end;
    }

    public function slug(): string
    {
        return AdminPageEnum::TOOLS;
    }

    public function title(): string
    {
        return 'Tools';
    }

    public function description(): string
    {
        return '';
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
