<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\DefinitionView;
use PhpArchitecture\StateMachine\Presentation\View\NodeView;
use PhpArchitecture\StateMachine\Presentation\View\TransitionView;

class DefinitionMappingStrategy implements StateMachineViewMappingStrategy
{
    public function __construct(
        private NodeMappingStrategy $nodeStrategy,
        private TransitionMappingStrategy $transitionStrategy,
    ) {}

    public function supports(object $resource): bool
    {
        return $resource instanceof Definition;
    }

    public function mapToView(object $resource): DefinitionView
    {
        if (!$resource instanceof Definition) {
            throw new UnsupportedResourceException(
                sprintf('DefinitionMappingStrategy only supports %s, got %s.', Definition::class, get_class($resource)),
            );
        }

        // Get ALL nodes from vertexStore - ports are also nodes
        $nodeViews = [];
        foreach ($resource->vertexStore->getVertices() as $vertex) {
            if ($vertex instanceof NodeInterface) {
                $nodeViews[] = $this->nodeStrategy->mapToView($vertex);
            }
        }

        // Get ALL transitions from edgeStore
        $transitionViews = [];
        foreach ($resource->edgeStore->getEdges() as $transition) {
            if ($transition instanceof TransitionInterface) {
                $transitionViews[] = $this->transitionStrategy->mapToView($transition);
            }
        }

        return new DefinitionView(
            class: get_class($resource),
            nodes: $nodeViews,
            inputPorts: [],
            outputPorts: [],
            transitions: $transitionViews,
        );
    }
}
