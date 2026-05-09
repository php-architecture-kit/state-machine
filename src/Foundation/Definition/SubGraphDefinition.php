<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;

class SubGraphDefinition extends Definition
{
    /**
     * @param array<string,array{node:NodeInterface,condition?:TransitionCondition|callable}> $inputs
     * @param array<string,array{node:NodeInterface,condition?:TransitionCondition|callable}> $outputs
     */
    public static function create(
        Graph $graph,
        array $inputs,
        array $outputs,
    ): static {
        $instance = static::newInstance(
            inputs: array_keys($inputs),
            outputs: array_keys($outputs),
        );

        /** @var array<string,TransitionInterface> $transitions */
        $transitions = $graph->edgeStore->getEdges(static fn(EdgeInterface $edge): bool => $edge instanceof TransitionInterface);
        /** @var array<string,NodeInterface> $nodes */
        $nodes = $graph->vertexStore->getVertices(static fn(VertexInterface $vertex): bool => $vertex instanceof NodeInterface);

        foreach ($nodes as $node) {
            $instance->addNode($node);
        }

        foreach ($transitions as $transition) {
            $instance->addTransition($transition->u(), $transition->v(), $transition->condition());
        }

        foreach ($inputs as $portName => $config) {
            $instance->addTransition($instance->input->{$portName}, $config['node'], $config['condition'] ?? null);
        }

        foreach ($outputs as $portName => $config) {
            $instance->addTransition($config['node'], $instance->output->{$portName}, $config['condition'] ?? null);
        }

        return $instance;
    }
}
