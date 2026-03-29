<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;
use DigitalRoyalty\Beacon\Support\Enums\PluginEnum;

final class HomeScreen extends AbstractAdminScreen
{
    public function slug(): string     { return ScreenEnum::HOME; }
    public function pageTitle(): string { return PluginEnum::NAME; }
    public function menuTitle(): string { return PluginEnum::NAME; }
    public function icon(): ?string
    {
        // Beacon signal: radiating arcs + centre dot.
        // Monochrome — WordPress applies its own colour via CSS filter.
        // Two nested hexagons with fill-rule="evenodd" punch a hole in the outer shape,
        // producing a solid ring — no strokes, no transparent fill inheritance issues.
        // Inner hex is the outer scaled ~0.65x from centre (10,10).
        // Ring width ~2.8 units — noticeably thick without overwhelming the centre dot.
        $svg = '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">'
             . '<path fill="black" fill-rule="evenodd"'
             . ' d="M10,2 16,6 16,14 10,18 4,14 4,6Z M10,4.8 13.9,7.4 13.9,12.6 10,15.2 6.1,12.6 6.1,7.4Z"/>'
             . '<circle cx="10" cy="10" r="2.2" fill="black"/>'
             . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    public function position(): ?int   { return 58; }

    public function render(): void { $this->renderSpa(); }
}
