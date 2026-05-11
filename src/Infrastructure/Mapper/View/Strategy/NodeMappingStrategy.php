<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\NodeView;

class NodeMappingStrategy implements StateMachineViewMappingStrategy
{
    public function supports(object $resource): bool
    {
        return $resource instanceof NodeInterface;
    }

    public function mapToView(object $resource): NodeView
    {
        if (!$resource instanceof NodeInterface) {
            throw new UnsupportedResourceException(
                sprintf('NodeMappingStrategy only supports %s, got %s.', NodeInterface::class, get_class($resource)),
            );
        }

        $globallyUniqueName = $resource instanceof Node
            ? $resource->globallyUniqueName
            : $resource->id()->toString();

        return new NodeView(
            id: $resource->id()->toString(),
            class: get_class($resource),
            globallyUniqueName: $globallyUniqueName,
            handlerClass: $resource->handlerClass(),
            transitionSelectionStrategy: get_class($resource->transitionStrategy()),
            tags: $resource->tags(),
        );
    }
}
