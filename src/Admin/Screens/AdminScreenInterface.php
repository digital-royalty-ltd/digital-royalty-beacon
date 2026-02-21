<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

interface AdminScreenInterface
{
    public function slug(): string;
    public function parentSlug(): ?string;

    public function pageTitle(): string;
    public function menuTitle(): string;
    public function capability(): string;

    public function icon(): ?string;
    public function position(): ?int;

    public function render(): void;
}
