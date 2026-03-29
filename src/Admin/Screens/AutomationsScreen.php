<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class AutomationsScreen extends AbstractAdminScreen
{
    public function slug(): string      { return ScreenEnum::AUTOMATIONS; }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string  { return 'Automations'; }
    public function menuTitle(): string  { return 'Automations'; }

    public function render(): void { $this->renderSpa(); }
}
