<?php

namespace DigitalRoyalty\Beacon\Admin\Tools;

final class MetaGeneratorPage implements ToolPageInterface
{
    public function slug(): string { return 'meta-generator'; }

    public function title(): string { return 'Meta Generator'; }

    public function description(): string
    {
        return 'Generate meta titles and descriptions for posts and pages.';
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
