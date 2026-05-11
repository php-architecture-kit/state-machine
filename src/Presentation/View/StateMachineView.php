<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

use PhpArchitecture\StateMachine\Presentation\View\EdgeView;
use PhpArchitecture\StateMachine\Presentation\View\NodeView;
use PhpArchitecture\StateMachine\Presentation\View\TransitionView;
use PhpArchitecture\StateMachine\Presentation\View\VertexView;

class StateMachineView
{
    /**
     * @param NodeView[]   $nodes
     * @param TransitionView[] $transitions
     * @param VertexView[] $unknownVertices
     * @param EdgeView[]   $unknownEdges
     * @param array<string, mixed> $__otherProperties
     */
    public function __construct(
        public readonly string $class,
        public readonly array $nodes,
        public readonly array $transitions,
        public readonly array $unknownVertices = [],
        public readonly array $unknownEdges = [],
        public readonly array $__otherProperties = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge(
            [
                'class'           => $this->class,
                'nodes'           => array_map(static fn(NodeView $n) => $n->toArray(), $this->nodes),
                'transitions'     => array_map(static fn(TransitionView $t) => $t->toArray(), $this->transitions),
                'unknownVertices' => array_map(static fn(VertexView $v) => $v->toArray(), $this->unknownVertices),
                'unknownEdges'    => array_map(static fn(EdgeView $e) => $e->toArray(), $this->unknownEdges),
            ],
            $this->__otherProperties,
        );
    }
}
