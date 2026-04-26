<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

/**
 * "Insights" hub — single page that surfaces every available signal lookup
 * (backlinks, GSC queries, SERP, etc.) as a tile. New signal operations
 * appear here as they're added to the Laravel signals registry.
 */
final class InsightsScreen extends AbstractAdminScreen
{
    public function slug(): string { return ScreenEnum::INSIGHTS; }

    public function parentSlug(): ?string { return ScreenEnum::HOME; }

    public function pageTitle(): string { return 'Insights'; }

    public function menuTitle(): string { return 'Insights'; }

    public function render(): void { $this->renderSpa(); }
}
