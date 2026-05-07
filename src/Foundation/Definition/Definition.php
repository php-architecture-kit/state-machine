<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition;

use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Index\IncidenceIndex;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\Technical\Assert;
use LogicException;

abstract class Definition extends Graph
{
    /** 
     * @param object<string,Port> $input
     * @param object<string,Port> $output
     */
    protected function __construct(
        public readonly object $input,
        public readonly object $output,
    ) {
        parent::__construct();
    }

    protected function addNode(NodeInterface $node): static
    {
        $this->vertexStore->addVertex($node);

        return $this;
    }

    protected function addTransition(NodeId $from, NodeId $to, ?TransitionCondition $condition = null): static
    {
        $this->edgeStore->addEdge(Transition::create($from, $to, $condition));

        return $this;
    }

    /** 
     * @return array{NodeInterface[],TransitionInterface[]} 
     */
    public function getDefinedNodesAndTransitions(): array
    {
        /** @var array<string,NodeInterface> $nodes */
        $nodes = $this->vertexStore->getVertices();
        Assert::eachInstanceOf($nodes, NodeInterface::class);

        /** @var array<string,TransitionInterface> $transitions */
        $transitions = $this->edgeStore->getEdges();
        Assert::eachInstanceOf($transitions, TransitionInterface::class);

        /** @var ?IncidenceIndex $incidenceIndex */
        $incidenceIndex = $this->indexRegistry->index(IncidenceIndex::class);
        if (null === $incidenceIndex) {
            $incidenceIndex = new IncidenceIndex();
            array_map(static fn(TransitionInterface $transition) => $incidenceIndex->onEdgeAdded($transition), $transitions);
        }

        /** @var NodeInterface[] $definedNodes */
        $definedNodes = [];
        foreach ($nodes as $node) {
            if (!$node instanceof Port) {
                $definedNodes[] = $node;
                continue;
            }

            /** @var string[] $nodeTransitions */
            $nodeTransitions = array_keys($incidenceIndex->edgesFor($node->id));

            // port without attached node must be omitted and it's transitions removed
            if ($node->attachedNode === null) {
                $transitions = array_filter($transitions, static fn(TransitionInterface $tr): bool => !in_array($tr->id()->toString(), $nodeTransitions, true));
                continue;
            }

            // port id must be replaced with attached node id in each incidence transition
            $nodeId = $node->attachedNode;
            foreach ($nodeTransitions as $transitionId) {
                $transition = $transitions[$transitionId];
                if ($transition->u()->equals($node->id())) {
                    $transitions[$transitionId] = $transition->withFrom($nodeId);
                } elseif ($transition->v()->equals($node->id())) {
                    $transitions[$transitionId] = $transition->withTo($nodeId);
                } else {
                    throw new LogicException('Port node must be either source or target of transition');
                }
            }
        }

        return [$definedNodes, $transitions];
    }
}
