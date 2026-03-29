<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

interface AutomationInterface
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * Reports this automation depends on before it can run.
     *
     * @return AutomationDependency[]
     */
    public function dependencies(): array;

    /**
     * The deferred request key used when this automation runs as a background job.
     * Returns null for interactive tools (e.g. Content Generator) that do not
     * have a single "run everything" trigger.
     */
    public function deferredKey(): ?string;
}
