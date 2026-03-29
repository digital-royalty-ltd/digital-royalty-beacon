<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class MissionControlScreen extends AbstractAdminScreen
{
    public function slug(): string        { return ScreenEnum::CAMPAIGNS; }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string   { return 'Campaigns'; }
    public function menuTitle(): string   { return 'Campaigns'; }

    public function render(): void { $this->renderSpa(); }
}
