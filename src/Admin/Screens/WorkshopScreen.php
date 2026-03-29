<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class WorkshopScreen extends AbstractAdminScreen
{
    public function slug(): string      { return ScreenEnum::WORKSHOP; }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string  { return 'Workshop'; }
    public function menuTitle(): string  { return 'Workshop'; }

    public function render(): void { $this->renderSpa(); }
}
