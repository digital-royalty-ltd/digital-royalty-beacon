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

    /**
     * Marketing categories this automation belongs to.
     *
     * @return string[]  AutomationCategoryEnum constants
     */
    public function categories(): array;

    /**
     * Execution modes this automation supports.
     *
     * - single:    Run once per user action (always supported).
     * - multiple:  Apply the same operation to multiple items.
     * - scheduled: Run automatically on a recurring schedule.
     *
     * @return string[]  AutomationModeEnum constants
     */
    public function supportedModes(): array;

    /**
     * JSON Schema describing the parameters this automation accepts.
     *
     * The schema is OpenAI function-calling compatible — AI agents read this
     * to understand what the automation does and construct valid calls.
     *
     * Return shape:
     *   [
     *     'type' => 'object',
     *     'properties' => [...],
     *     'required' => [...],
     *   ]
     *
     * @return array<string, mixed>
     */
    public function parameterSchema(): array;

    /**
     * Uniform entry point that executes the automation.
     *
     * Every trigger path — user click, agent call, scheduler tick, API request —
     * funnels through this method. Implementations MUST:
     *   1. Validate the parameters against parameterSchema()
     *   2. Check dependencies (or skip if explicitly bypassed)
     *   3. Perform the work (sync) or dispatch a deferred request (async)
     *   4. Return an InvocationResult with the outcome
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult;
}
