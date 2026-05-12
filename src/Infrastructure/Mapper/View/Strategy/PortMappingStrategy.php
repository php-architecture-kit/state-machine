<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Presentation\View\PortView;

class PortMappingStrategy implements StateMachineViewMappingStrategy
{
    /** @param Port $resource */
    public function supports(object $resource): bool
    {
        return $resource instanceof Port;
    }

    /** @param Port $resource */
    public function mapToView(object $resource): PortView
    {
        // Port extends Node, so it has globallyUniqueName property
        $name = $resource->globallyUniqueName ?? $resource->id()->toString();
        $attachedNodeId = $resource->attachedNode?->toString();
        return new PortView(
            name: $name,
            attachedNodeId: $attachedNodeId,
        );
    }
}
