<?php

namespace DigitalRoyalty\Beacon\Admin\Tools;

interface ToolPageInterface
{
    public function slug(): string;

    public function title(): string;

    public function description(): string;

    public function isAvailable(): bool;

    public function render(): void;
}
