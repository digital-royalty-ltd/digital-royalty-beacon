<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class ConfigurationScreen extends AbstractAdminScreen
{
    public function slug(): string      { return ScreenEnum::CONFIGURATION; }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string  { return 'Configuration'; }
    public function menuTitle(): string  { return 'Configuration'; }

    public function render(): void { $this->renderSpa(); }
}
