<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

abstract class AbstractAdminScreen implements AdminScreenInterface
{
    public function parentSlug(): ?string { return null; }
    public function capability(): string { return 'manage_options'; }
    public function icon(): ?string { return null; }
    public function position(): ?int { return null; }

    protected function renderSpa(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap" style="margin:0;padding:0;"><div id="beacon-root"></div></div>';
    }
}