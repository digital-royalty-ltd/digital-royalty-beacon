<?php

namespace DigitalRoyalty\Beacon\Systems\Actions;

use DigitalRoyalty\Beacon\Systems\Actions\Invokers\AddInternalLinkInvoker;
use DigitalRoyalty\Beacon\Systems\Actions\Invokers\PublishDraftInvoker;
use DigitalRoyalty\Beacon\Systems\Actions\Invokers\UpdatePageMetaInvoker;
use DigitalRoyalty\Beacon\Systems\Actions\Invokers\UpdatePostExcerptInvoker;

/**
 * Registry of action invokers the AutomationRequestPoller dispatches to when
 * a pulled request has kind=action.
 *
 * Add new action verbs by:
 *   1. Implement ActionInvokerInterface in src/Systems/Actions/Invokers/
 *   2. Register the new instance in all() below
 *   3. Add the matching action in config/beacon-actions.php on the Laravel side
 *      with transport=adapter so the dispatcher routes through this pipe.
 */
final class ActionInvokerRegistry
{
    /**
     * @return ActionInvokerInterface[]
     */
    public function all(): array
    {
        return [
            new UpdatePageMetaInvoker(),
            new AddInternalLinkInvoker(),
            new UpdatePostExcerptInvoker(),
            new PublishDraftInvoker(),
        ];
    }

    public function find(string $slug): ?ActionInvokerInterface
    {
        foreach ($this->all() as $invoker) {
            if ($invoker->slug() === $slug) {
                return $invoker;
            }
        }

        return null;
    }
}
