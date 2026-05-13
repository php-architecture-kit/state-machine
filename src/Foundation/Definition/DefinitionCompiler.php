<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition;

use PhpArchitecture\Graph\Index\IncidenceIndex;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\Technical\Assert;
use LogicException;

final class DefinitionCompiler
{
    /** @var array<string,Port> */
    private array $portMap = [];

    /**
     * @return array{NodeInterface[],TransitionInterface[]}
     */
    public function compile(Definition $definition): array
    {
        [$nodes, $transitions] = $this->extractNodesAndTransitions($definition);
        $this->portMap = $this->buildPortMap($nodes);
        $incidenceIndex = $this->initializeIncidenceIndex($transitions);

        /** @var NodeInterface[] $definedNodes */
        $definedNodes = [];
        foreach ($nodes as $node) {
            if (!$node instanceof Port) {
                $definedNodes[] = $node;
                continue;
            }

            $this->processPort($node, $transitions, $incidenceIndex);
        }

        return [$definedNodes, $transitions];
    }

    /**
     * @param array<string,NodeInterface> $nodes
     * @return array<string,Port>
     */
    private function buildPortMap(array $nodes): array
    {
        $portMap = [];
        foreach ($nodes as $node) {
            if ($node instanceof Port) {
                $portMap[$node->id()->toString()] = $node;
            }
        }

        return $portMap;
    }

    /**
     * @return array{array<string,NodeInterface>,array<string,TransitionInterface>}
     */
    private function extractNodesAndTransitions(Definition $definition): array
    {
        /** @var array<string,NodeInterface> $nodes */
        $nodes = $definition->vertexStore->getVertices();
        Assert::eachInstanceOf($nodes, NodeInterface::class);

        /** @var array<string,TransitionInterface> $transitions */
        $transitions = $definition->edgeStore->getEdges();
        Assert::eachInstanceOf($transitions, TransitionInterface::class);

        return [$nodes, $transitions];
    }

    /**
     * @param array<string,TransitionInterface> $transitions
     */
    private function initializeIncidenceIndex(array $transitions): IncidenceIndex
    {
        $incidenceIndex = new IncidenceIndex();
        array_map(
            static fn(TransitionInterface $transition) => $incidenceIndex->onEdgeAdded($transition),
            $transitions,
        );

        return $incidenceIndex;
    }

    /**
     * @param array<string,TransitionInterface> $transitions
     */
    private function processPort(Port $port, array &$transitions, IncidenceIndex $incidenceIndex): void
    {
        $nodeTransitions = $this->getTransitionIdsForPort($port, $incidenceIndex);

        $resolvedNodeId = $this->resolveAttachedNode($port);

        if ($resolvedNodeId === null) {
            $this->removePortTransitions($nodeTransitions, $transitions);

            return;
        }

        $this->replacePortWithAttachedNode($port, $nodeTransitions, $transitions, $resolvedNodeId);
    }

    private function resolveAttachedNode(Port $port): ?NodeId
    {
        $current = $port->attachedNode;
        $visited = [];

        while ($current instanceof Port) {
            $currentId = $current->id()->toString();

            if (isset($visited[$currentId])) {
                throw new LogicException('Circular port attachment detected');
            }

            $visited[$currentId] = true;

            if (!isset($this->portMap[$currentId])) {
                throw new LogicException(sprintf(
                    'Port "%s" is attached to Port "%s" which is not in the definition',
                    $port->id()->toString(),
                    $currentId,
                ));
            }

            $current = $current->attachedNode;
        }

        return $current;
    }

    /**
     * @return string[]
     */
    private function getTransitionIdsForPort(Port $port, IncidenceIndex $incidenceIndex): array
    {
        return array_keys($incidenceIndex->edgesFor($port->id));
    }

    /**
     * @param string[] $nodeTransitions
     * @param array<string,TransitionInterface> $transitions
     */
    private function removePortTransitions(array $nodeTransitions, array &$transitions): void
    {
        $transitions = array_filter(
            $transitions,
            static fn(TransitionInterface $tr): bool => !in_array($tr->id()->toString(), $nodeTransitions, true),
        );
    }

    /**
     * @param string[] $nodeTransitions
     * @param array<string,TransitionInterface> $transitions
     */
    private function replacePortWithAttachedNode(
        Port $port,
        array $nodeTransitions,
        array &$transitions,
        NodeId $attachedNodeId,
    ): void {
        foreach ($nodeTransitions as $transitionId) {
            if (!isset($transitions[$transitionId])) {
                continue;
            }

            $transition = $transitions[$transitionId];
            $updatedTransition = $this->createUpdatedTransition($port, $transition, $attachedNodeId);

            if ($updatedTransition === null) {
                throw new LogicException('Port node must be either source or target of transition');
            }

            $transitions[$transitionId] = $updatedTransition;
        }
    }

    private function createUpdatedTransition(
        Port $port,
        TransitionInterface $transition,
        NodeId $attachedNodeId,
    ): ?TransitionInterface {
        if ($transition->u()->equals($port->id())) {
            return $transition->withInput($attachedNodeId);
        }

        if ($transition->v()->equals($port->id())) {
            return $transition->withOutput($attachedNodeId);
        }

        return null;
    }
}
