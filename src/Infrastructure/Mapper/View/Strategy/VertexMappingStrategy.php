<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\Graph\Vertex\VertexInterface;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\VertexView;

class VertexMappingStrategy implements StateMachineViewMappingStrategy
{
    public function supports(object $resource): bool
    {
        return $resource instanceof VertexInterface && !$resource instanceof NodeInterface;
    }

    public function mapToView(object $resource): VertexView
    {
        if (!$resource instanceof VertexInterface) {
            throw new UnsupportedResourceException(
                sprintf('VertexMappingStrategy only supports %s, got %s.', VertexInterface::class, get_class($resource)),
            );
        }

        return new VertexView(
            id: $resource->id()->toString(),
            class: get_class($resource),
        );
    }
}
