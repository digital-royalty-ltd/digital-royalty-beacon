<?php

namespace DigitalRoyalty\Beacon\Systems\Actions;

use DigitalRoyalty\Beacon\Systems\Automations\InvocationActor;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationResult;

/**
 * One implementation per atomic action slug — wp.page.update_meta,
 * wp.post.add_internal_link, etc.
 *
 * Actions are the granular adapter-side verbs the agent reaches for when no
 * named automation fits the task. They run synchronously inside this PHP
 * process (no deferred queue) and report their outcome back through the
 * standard InvocationResult shape so the AutomationRequestPoller can use the
 * same complete/fail flow as workflow automations.
 */
interface ActionInvokerInterface
{
    /**
     * The exact slug from the Laravel action registry — e.g. `wp.page.update_meta`.
     */
    public function slug(): string;

    /**
     * Execute the action against this WordPress install.
     *
     * @param  array<string, mixed>  $parameters  Args from the action_request payload.
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult;
}
