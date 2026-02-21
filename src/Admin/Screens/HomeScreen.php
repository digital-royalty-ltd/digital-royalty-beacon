<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Admin\Views\HomeView;
use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class HomeScreen extends AbstractAdminScreen
{
    public function __construct(private readonly HomeView $view) {}

    public function slug(): string { return $this->view->slug(); }
    public function pageTitle(): string { return $this->view->title(); }
    public function menuTitle(): string { return $this->view->title(); }
    public function icon(): ?string { return 'dashicons-admin-site-alt3'; }
    public function position(): ?int { return 58; }

    public function render(): void { $this->view->render(); }
}
