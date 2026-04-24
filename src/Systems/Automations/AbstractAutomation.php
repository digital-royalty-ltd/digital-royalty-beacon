<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

/**
 * Base class providing sensible defaults for the AutomationInterface.
 *
 * Existing automations can extend this to inherit the default implementations
 * of parameterSchema() and invoke(), and override only what they need.
 *
 * New automations should always implement parameterSchema() and invoke()
 * themselves. The defaults here exist to avoid breaking existing code during
 * the contract migration.
 */
abstract class AbstractAutomation implements AutomationInterface
{
    /**
     * Default empty schema. Override in concrete automations to describe
     * the parameters this automation accepts.
     *
     * @return array<string, mixed>
     */
    public function parameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [],
            'required'   => [],
        ];
    }

    /**
     * Default stub that signals this automation has not yet been migrated
     * to the invoke() contract. Existing REST controllers still work for
     * now — this is called only when an agent or API consumer directly
     * invokes the automation.
     *
     * Concrete automations should override this with their real logic.
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        return InvocationResult::failed(
            "Automation '{$this->key()}' has not yet implemented the invoke() contract.",
            'invoke_not_implemented',
        );
    }
}
