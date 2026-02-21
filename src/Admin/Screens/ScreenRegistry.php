<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

final class ScreenRegistry
{
    /** @var array<string, AdminScreenInterface> */
    private array $screens = [];

    /** @param AdminScreenInterface[] $screens */
    public function __construct(array $screens)
    {
        foreach ($screens as $screen) {
            $this->screens[$screen->slug()] = $screen;
        }
    }

    /** @return AdminScreenInterface[] */
    public function topLevel(): array
    {
        return array_values(array_filter(
            $this->screens,
            static fn (AdminScreenInterface $s) => $s->parentSlug() === null
        ));
    }

    /** @return AdminScreenInterface[] */
    public function childrenOf(string $parentSlug): array
    {
        return array_values(array_filter(
            $this->screens,
            static fn (AdminScreenInterface $s) => $s->parentSlug() === $parentSlug
        ));
    }
}