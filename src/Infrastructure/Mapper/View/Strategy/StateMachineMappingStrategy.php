<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\NodeView;
use PhpArchitecture\StateMachine\Presentation\View\StateMachineView;
use PhpArchitecture\StateMachine\Presentation\View\TransitionView;

class StateMachineMappingStrategy implements StateMachineViewMappingStrategy
{
    /**
     * @param ?VertexMappingStrategy $vertexStrategy deprecated, unused: a StateMachine exposes only its nodes
     * @param ?EdgeMappingStrategy   $edgeStrategy   deprecated, unused: a StateMachine exposes only its transitions
     */
    public function __construct( // @phpstan-ignore constructor.unusedParameter, constructor.unusedParameter (both kept for BC)
        private readonly NodeMappingStrategy $nodeStrategy,
        private readonly TransitionMappingStrategy $transitionStrategy,
        ?VertexMappingStrategy $vertexStrategy = null,
        ?EdgeMappingStrategy $edgeStrategy = null,
    ) {}

    public function supports(object $resource): bool
    {
        return $resource instanceof StateMachine;
    }

    public function mapToView(object $resource): StateMachineView
    {
        if (!$resource instanceof StateMachine) {
            throw new UnsupportedResourceException(
                sprintf('StateMachineMappingStrategy only supports %s, got %s.', StateMachine::class, get_class($resource)),
            );
        }

        $nodes = array_map(
            fn(NodeInterface $node): NodeView => $this->nodeStrategy->mapToView($node),
            array_values($resource->getNodes()),
        );
        $transitions = array_map(
            fn(Transition $transition): TransitionView => $this->transitionStrategy->mapToView($transition),
            $resource->getTransitions(),
        );

        return new StateMachineView(
            class: get_class($resource),
            nodes: $nodes,
            transitions: $transitions,
        );
    }
}
