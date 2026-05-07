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
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionConditionCallback;

abstract class Definition extends Graph
{
    public readonly object $input;
    public readonly object $output;

    protected function __construct(
        object $input,
        object $output,
    ) {
        $this->input = $input;
        $this->output = $output;
        parent::__construct();
    }

    /**
     * @param string[] $inputs
     * @param string[] $outputs
     */
    protected static function newInstance(array $inputs, array $outputs): static
    {
        $input = (object) array_combine($inputs, array_map(static fn($input): Port => new Port($input), $inputs));
        $output = (object) array_combine($outputs, array_map(static fn($output): Port => new Port($output), $outputs));

        /** @phpstan-ignore-next-line */
        $instance = new static($input, $output);
        $portCollections = [$input, $output];
        foreach ($portCollections as $portCollection) {
            foreach ((array) $portCollection as $port) {
                /** @var Port $port */
                $instance->addNode($port);
            }
        }

        return $instance;
    }

    protected function addNode(NodeInterface $node): static
    {
        $this->vertexStore->addVertex($node);

        return $this;
    }

    /**
     * @param null|TransitionCondition|callable(States):TransitionConditionDecision $condition
     */
    protected function addTransition(NodeId|NodeInterface $from, NodeId|NodeInterface $to, null|callable|TransitionCondition $condition = null): static
    {
        foreach (['from', 'to'] as $node) {
            if (${$node} instanceof NodeInterface) {
                if (!$this->vertexStore->hasVertex(${$node}->id())) { // @phpstan-ignore-line
                    $this->addNode(${$node}); // @phpstan-ignore-line
                }

                ${$node} = ${$node}->id; // @phpstan-ignore-line
            }
        }

        if (is_callable($condition)) {
            $condition = TransitionConditionCallback::define($condition);
        }

        $this->edgeStore->addEdge(Transition::create($from, $to, $condition)); // @phpstan-ignore-line

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
