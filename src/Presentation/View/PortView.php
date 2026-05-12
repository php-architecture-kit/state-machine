<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

class PortView
{
    /**
     * @param string|null $attachedNodeId ID of attached node, if any
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $attachedNodeId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'shortName' => $this->extractShortName($this->name),
            'attachedNodeId' => $this->attachedNodeId,
        ];
    }

    private function extractShortName(string $name): string
    {
        // Extract last segment from dotted name like "state-machine.retry.test.input.trigger"
        $parts = explode('.', $name);
        return end($parts) ?: $name;
    }
}
