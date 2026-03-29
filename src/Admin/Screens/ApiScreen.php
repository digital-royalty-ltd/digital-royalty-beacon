<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class ApiScreen extends AbstractAdminScreen
{
    public function slug(): string        { return ScreenEnum::API; }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string   { return 'API'; }
    public function menuTitle(): string   { return 'API'; }

    public function render(): void { $this->renderSpa(); }
}
