<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

use DigitalRoyalty\Beacon\Admin\Views\Tools\ContentGeneratorView;
use DigitalRoyalty\Beacon\Admin\Views\Tools\GapSuggestionsView;
use DigitalRoyalty\Beacon\Admin\Views\Tools\MetaGeneratorView;
use DigitalRoyalty\Beacon\Admin\Views\ToolsView;
use DigitalRoyalty\Beacon\Admin\Views\ViewInterface;
use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class ToolsScreen extends AbstractAdminScreen
{
    /** @var ViewInterface[] */
    private array $tools;

    public function __construct(private readonly ToolsView $view)
    {
        $this->tools = [
            new ContentGeneratorView(),
            new MetaGeneratorView(),
            new GapSuggestionsView(),
        ];
    }

    public function slug(): string { return $this->view->slug(); }
    public function parentSlug(): ?string { return ScreenEnum::HOME; }
    public function pageTitle(): string { return $this->view->title(); }
    public function menuTitle(): string { return $this->view->title(); }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $toolSlug = '';
        if (isset($_GET['tool'])) {
            $toolSlug = sanitize_key(wp_unslash((string) $_GET['tool']));
        }

        // No tool selected: show tools overview
        if ($toolSlug === '') {
            $this->view->render();
            return;
        }

        $tool = $this->findTool($toolSlug);

        // Unknown tool slug: fallback to overview
        if (!$tool) {
            $this->view->render();
            return;
        }

        // Tool exists but unavailable: show coming soon
        if (!$tool->isAvailable()) {
            $this->view->renderComingSoon($tool);
            return;
        }

        // Tool exists and available: render tool page
        $tool->render();
    }

    private function findTool(string $slug): ?ViewInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->slug() === $slug) {
                return $tool;
            }
        }
        return null;
    }
}