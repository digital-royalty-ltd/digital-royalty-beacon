<?php

namespace DigitalRoyalty\Beacon\Admin\Screens;

abstract class AbstractAdminScreen implements AdminScreenInterface
{
    public function parentSlug(): ?string { return null; }
    public function capability(): string { return 'manage_options'; }
    public function icon(): ?string { return null; }
    public function position(): ?int { return null; }
}