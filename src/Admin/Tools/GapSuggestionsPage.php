<?php

namespace DigitalRoyalty\Beacon\Admin\Tools;

final class GapSuggestionsPage implements ToolPageInterface
{
    public function slug(): string { return 'gap-suggestions'; }

    public function title(): string { return 'Gap Suggestions'; }

    public function description(): string
    {
        return 'Analyze your site and suggest missing pages and topics.';
    }

    public function isAvailable(): bool { return false; }

    public function render(): void
    {
        $backUrl = add_query_arg(['page' => 'dr-beacon'], admin_url('options-general.php'));
        ?>
        <p><a href="<?php echo esc_url($backUrl); ?>" class="button">← Back to Tools</a></p>
        <h2><?php echo esc_html($this->title()); ?></h2>
        <p class="description"><?php echo esc_html($this->description()); ?></p>
        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
            <p class="description" style="margin:0;">Coming soon.</p>
        </div>
        <?php
    }
}
