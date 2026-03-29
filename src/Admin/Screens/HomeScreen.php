<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class HomeScreen extends AbstractAdminScreen
{
    public function slug(): string     { return ScreenEnum::HOME; }
    public function pageTitle(): string { return 'Beacon AI'; }
    public function menuTitle(): string { return 'Beacon AI'; }
    public function icon(): ?string    { return 'dashicons-admin-site-alt3'; }
    public function position(): ?int   { return 58; }

    public function render(): void { $this->renderSpa(); }
}
