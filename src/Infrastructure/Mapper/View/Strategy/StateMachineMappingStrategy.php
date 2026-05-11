<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\EdgeView;
use PhpArchitecture\StateMachine\Presentation\View\NodeView;
use PhpArchitecture\StateMachine\Presentation\View\StateMachineView;
use PhpArchitecture\StateMachine\Presentation\View\TransitionView;
use PhpArchitecture\StateMachine\Presentation\View\VertexView;
use ReflectionClass;

class StateMachineMappingStrategy implements StateMachineViewMappingStrategy
{
    public function __construct(
        private readonly NodeMappingStrategy $nodeStrategy,
        private readonly TransitionMappingStrategy $transitionStrategy,
        private readonly VertexMappingStrategy $vertexStrategy,
        private readonly EdgeMappingStrategy $edgeStrategy,
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

        $nodes           = [];
        $transitions     = [];
        $unknownVertices = [];
        $unknownEdges    = [];

        $reflection = new ReflectionClass($resource);
        $graphProp  = $reflection->getProperty('graph');
        $graph      = $graphProp->getValue($resource);

        foreach ($graph->vertexStore->getVertices() as $vertex) {
            if ($vertex instanceof NodeInterface) {
                $view = $this->nodeStrategy->mapToView($vertex);
                assert($view instanceof NodeView);
                $nodes[] = $view;
            } elseif ($vertex instanceof VertexInterface) {
                $view = $this->vertexStrategy->mapToView($vertex);
                assert($view instanceof VertexView);
                $unknownVertices[] = $view;
            }
        }

        foreach ($graph->edgeStore->getEdges() as $edge) {
            if ($edge instanceof TransitionInterface) {
                $view = $this->transitionStrategy->mapToView($edge);
                assert($view instanceof TransitionView);
                $transitions[] = $view;
            } elseif ($edge instanceof EdgeInterface) {
                $view = $this->edgeStrategy->mapToView($edge);
                assert($view instanceof EdgeView);
                $unknownEdges[] = $view;
            }
        }

        return new StateMachineView(
            class: get_class($resource),
            nodes: $nodes,
            transitions: $transitions,
            unknownVertices: $unknownVertices,
            unknownEdges: $unknownEdges,
        );
    }
}
