<?php

namespace DigitalRoyalty\Beacon\Admin\Views;

use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;


final class ConfigurationView implements ViewInterface
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?= $this->title() ?></h1>

        </div>
        <?php
    }

    public function slug(): string
    {
        return AdminPageEnum::CONFIGURATION;
    }

    public function title(): string
    {
        return 'Configuration';
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
