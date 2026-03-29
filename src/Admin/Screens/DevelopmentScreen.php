<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class DevelopmentScreen extends AbstractAdminScreen
{
    public function slug(): string        { return ScreenEnum::DEVELOPMENT; }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string   { return 'Development'; }
    public function menuTitle(): string   { return 'Development'; }

    public function render(): void { $this->renderSpa(); }
}
