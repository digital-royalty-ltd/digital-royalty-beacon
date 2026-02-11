<?php

namespace DigitalRoyalty\Beacon\Admin\Tools;

final class ContentGeneratorPage implements ToolPageInterface
{
    public function slug(): string
    {
        return 'content-generator';
    }

    public function title(): string
    {
        return 'Content Generator';
    }

    public function description(): string
    {
        return 'Generate a new draft post from a prompt. Choose a post type, generate content, then review and publish manually.';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function render(): void
    {
        $backUrl = add_query_arg([
            'page' => 'dr-beacon',
        ], admin_url('options-general.php'));

        ?>
        <p>
            <a href="<?php echo esc_url($backUrl); ?>" class="button">← Back to Tools</a>
        </p>

        <h2><?php echo esc_html($this->title()); ?></h2>

        <p class="description"><?php echo esc_html($this->description()); ?></p>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
            <h3 style="margin-top:0;">Next</h3>
            <ul style="list-style:disc;margin-left:18px;">
                <li>Post type selector (pages, posts, custom post types)</li>
                <li>Prompt input with optional settings (tone, length, keywords)</li>
                <li>Generate button (calls the Beacon API)</li>
                <li>Create draft in WordPress and open the editor automatically</li>
            </ul>
        </div>
        <?php
    }
}