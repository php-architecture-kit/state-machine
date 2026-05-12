<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

class DefinitionView
{
    /**
     * @param NodeView[]   $nodes
     * @param PortView[]   $inputPorts
     * @param PortView[]   $outputPorts
     * @param PortView[]   $internalPorts
     * @param TransitionView[] $transitions
     */
    public function __construct(
        public readonly string $class,
        public readonly array $nodes,
        public readonly array $inputPorts,
        public readonly array $outputPorts,
        public readonly array $transitions,
        public readonly array $internalPorts = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'class'         => $this->class,
            'nodes'         => array_map(static fn(NodeView $n) => $n->toArray(), $this->nodes),
            'inputPorts'    => array_map(static fn(PortView $p) => $p->toArray(), $this->inputPorts),
            'outputPorts'   => array_map(static fn(PortView $p) => $p->toArray(), $this->outputPorts),
            'internalPorts' => array_map(static fn(PortView $p) => $p->toArray(), $this->internalPorts),
            'transitions'   => array_map(static fn(TransitionView $t) => $t->toArray(), $this->transitions),
        ];
    }
}
