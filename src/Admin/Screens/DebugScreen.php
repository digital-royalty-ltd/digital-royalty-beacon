<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class DebugScreen extends AbstractAdminScreen
{
    public function slug(): string      { return ScreenEnum::DEBUG; }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string  { return 'Debug'; }
    public function menuTitle(): string  { return 'Debug'; }

    public function render(): void { $this->renderSpa(); }
}
