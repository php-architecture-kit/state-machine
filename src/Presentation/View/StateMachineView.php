<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

use PhpArchitecture\StateMachine\Presentation\View\NodeView;
use PhpArchitecture\StateMachine\Presentation\View\TransitionView;

class StateMachineView
{
    /**
     * @param NodeView[]   $nodes
     * @param TransitionView[] $transitions
     */
    public function __construct(
        public readonly string $class,
        public readonly array $nodes,
        public readonly array $transitions,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'class'       => $this->class,
            'nodes'       => array_map(static fn(NodeView $n) => $n->toArray(), $this->nodes),
            'transitions' => array_map(static fn(TransitionView $t) => $t->toArray(), $this->transitions),
        ];
    }
}
