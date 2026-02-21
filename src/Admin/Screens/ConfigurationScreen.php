<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Admin\Views\ConfigurationView;
use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class ConfigurationScreen extends AbstractAdminScreen
{
    public function __construct(private readonly ConfigurationView $view) {}

    public function slug(): string { return $this->view->slug(); }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string { return $this->view->title(); }
    public function menuTitle(): string { return $this->view->title(); }

    public function render(): void { $this->view->render(); }
}
