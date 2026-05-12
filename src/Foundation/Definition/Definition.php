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
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionConditionCallback;
use stdClass;

abstract class Definition extends Graph
{
    /**
     * @var stdClass<string,Port>
     * @phpstan-var stdClass<string,Port>
     */
    public readonly object $input;

    /**
     * @var stdClass<string,Port>
     * @phpstan-var stdClass<string,Port>
     */
    public readonly object $output;

    protected function __construct(
        public readonly string $name,
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
    protected static function newInstance(string $name, array $inputs, array $outputs): static
    {
        $input = (object) array_combine($inputs, array_map(static fn($input): Port => new Port("{$name}.{$input}"), $inputs));
        $output = (object) array_combine($outputs, array_map(static fn($output): Port => new Port("{$name}.{$output}"), $outputs));

        /** @phpstan-ignore-next-line */
        $instance = new static($name, $input, $output);
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
    protected function addTransition(NodeId|NodeInterface $input, NodeId|NodeInterface $output, null|callable|TransitionCondition $condition = null): static
    {
        foreach (['input', 'output'] as $node) {
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

        $this->edgeStore->addEdge(Transition::create($input, $output, $condition)); // @phpstan-ignore-line

        return $this;
    }

    /**
     * @param array<string,string|string[]> $inputPortMapping embedded outputs -> embedder inputs
     * @param array<string,string|string[]> $outputPortMapping embedder outputs -> embedded inputs
     */
    public function embed(Definition $embeddedDefinition, array $inputPortMapping, array $outputPortMapping): static
    {
        // connect ports
        foreach ($inputPortMapping as $embeddedPortName => $embedderPortNames) {
            if (is_string($embedderPortNames)) {
                $embedderPortNames = [$embedderPortNames];
            }

            foreach ($embedderPortNames as $embedderPortName) {
                $embeddedName = $embeddedDefinition->output->{$embeddedPortName}->name();
                $embedderName = $this->input->{$embedderPortName}->name();
                $passthrough = new PassthroughNode("state-machine.embedded.{$embeddedName}-{$embedderName}");
                $this->addNode($passthrough);
                $embeddedDefinition->output->{$embeddedPortName}->attach($passthrough->id);
                $this->input->{$embedderPortName}->attach($passthrough->id);
            }
        }

        foreach ($outputPortMapping as $embedderPortName => $embeddedPortNames) {
            if (is_string($embeddedPortNames)) {
                $embeddedPortNames = [$embeddedPortNames];
            }

            foreach ($embeddedPortNames as $embeddedPortName) {
                $embedderName = $this->output->{$embedderPortName}->name();
                $embeddedName = $embeddedDefinition->input->{$embeddedPortName}->name();
                $passthrough = new PassthroughNode("state-machine.embedded.{$embedderName}-{$embeddedName}");
                $this->addNode($passthrough);
                $this->output->{$embedderPortName}->attach($passthrough->id);
                $embeddedDefinition->input->{$embeddedPortName}->attach($passthrough->id);
            }
        }

        // add embedded definition nodes and transitions
        foreach ((array) $embeddedDefinition->vertexStore->getVertices() as $node) {
            $this->addNode($node);
        }

        foreach ((array) $embeddedDefinition->edgeStore->getEdges() as $transition) {
            $this->edgeStore->addEdge($transition);
        }

        return $this;
    }

    /**
     * @param array<string,string|string[]> $inputPortMapping embedded outputs -> embedder inputs (second -> first)
     * @param array<string,string|string[]> $outputPortMapping embedder outputs -> embedded inputs (first -> second)
     */
    public static function merge(
        string $name,
        Definition $first,
        Definition $second,
        array $inputPortMapping,
        array $outputPortMapping,
    ): static {
        // step 1: connect first and second via embed
        $first->embed($second, $inputPortMapping, $outputPortMapping);

        // step 2: resolve connected ports in first (persist attached ports to edgeStore)
        $incidenceIndex = $first->indexRegistry->index(IncidenceIndex::class);
        if (null === $incidenceIndex) {
            $incidenceIndex = new IncidenceIndex();
            /** @var array<string,TransitionInterface> $allTransitions */
            $allTransitions = $first->edgeStore->getEdges();
            array_map(static fn(TransitionInterface $tr) => $incidenceIndex->onEdgeAdded($tr), $allTransitions);
        }

        foreach ([(array) $first->input, (array) $first->output, (array) $second->input, (array) $second->output] as $portCollection) {
            foreach ($portCollection as $port) {
                /** @var Port $port */
                if (!$port instanceof Port || $port->attachedNode === null) {
                    continue;
                }

                $nodeId = $port->attachedNode;
                foreach ($incidenceIndex->edgesFor($port->id) as $transition) {
                    /** @var TransitionInterface $transition */
                    if ($transition->u()->equals($port->id())) {
                        $updated = $transition->withInput($nodeId);
                    } elseif ($transition->v()->equals($port->id())) {
                        $updated = $transition->withOutput($nodeId);
                    } else {
                        continue;
                    }

                    $first->edgeStore->removeEdge($transition->id());
                    $first->edgeStore->addEdge($updated);
                }
            }
        }

        // step 3: collect remaining empty ports from first's vertexStore (contains both first and second ports after embed)
        /** @var array<string,Port> $emptyInputPorts */
        $emptyInputPorts = [];
        /** @var array<string,Port> $emptyOutputPorts */
        $emptyOutputPorts = [];

        foreach ($first->vertexStore->getVertices() as $vertex) {
            if (!$vertex instanceof Port || $vertex->attachedNode !== null) {
                continue;
            }

            $isInput = array_search($vertex, (array) $first->input, true) !== false
                || array_search($vertex, (array) $second->input, true) !== false;

            if ($isInput) {
                $emptyInputPorts[$vertex->globallyUniqueName] = $vertex;
            } else {
                $emptyOutputPorts[$vertex->globallyUniqueName] = $vertex;
            }
        }

        // guard: detect duplicate short port names among free input/output ports
        $freeFirstInputNames = array_keys(array_filter((array) $first->input, static fn(Port $p): bool => $p->attachedNode === null));
        $freeSecondInputNames = array_keys(array_filter((array) $second->input, static fn(Port $p): bool => $p->attachedNode === null));
        $duplicateInputs = array_intersect($freeFirstInputNames, $freeSecondInputNames);
        if (!empty($duplicateInputs)) {
            throw new LogicException(sprintf(
                'Cannot merge definitions: duplicate input port names found: %s',
                implode(', ', $duplicateInputs),
            ));
        }

        $freeFirstOutputNames = array_keys(array_filter((array) $first->output, static fn(Port $p): bool => $p->attachedNode === null));
        $freeSecondOutputNames = array_keys(array_filter((array) $second->output, static fn(Port $p): bool => $p->attachedNode === null));
        $duplicateOutputs = array_intersect($freeFirstOutputNames, $freeSecondOutputNames);
        if (!empty($duplicateOutputs)) {
            throw new LogicException(sprintf(
                'Cannot merge definitions: duplicate output port names found: %s',
                implode(', ', $duplicateOutputs),
            ));
        }

        // step 4: create wrapper with same port names and embed connected definition
        $wrapper = static::newInstance($name, array_keys($emptyInputPorts), array_keys($emptyOutputPorts));
        $wrapper->embed($first, [], []);

        // step 5: replace remaining empty ports of connected definition with wrapper ports
        /** @var ?IncidenceIndex $wrapperIncidenceIndex */
        $wrapperIncidenceIndex = $wrapper->indexRegistry->index(IncidenceIndex::class);
        if (null === $wrapperIncidenceIndex) {
            $wrapperIncidenceIndex = new IncidenceIndex();
            /** @var array<string,TransitionInterface> $wrapperTransitions */
            $wrapperTransitions = $wrapper->edgeStore->getEdges();
            array_map(static fn(TransitionInterface $tr) => $wrapperIncidenceIndex->onEdgeAdded($tr), $wrapperTransitions);
        }

        foreach ($emptyInputPorts as $portName => $connectedPort) {
            $wrapperPort = $wrapper->input->{$portName};

            $updatedTransitions = array_map(
                static fn(TransitionInterface $transition): TransitionInterface => $transition->withInput($wrapperPort->id),
                $wrapperIncidenceIndex->edgesFor($connectedPort->id),
            );

            $wrapper->vertexStore->removeVertex($connectedPort->id);

            foreach ($updatedTransitions as $transition) {
                $wrapper->edgeStore->addEdge($transition);
            }
        }

        foreach ($emptyOutputPorts as $portName => $connectedPort) {
            $wrapperPort = $wrapper->output->{$portName};

            $updatedTransitions = array_map(
                static fn(TransitionInterface $transition): TransitionInterface => $transition->withOutput($wrapperPort->id),
                $wrapperIncidenceIndex->edgesFor($connectedPort->id),
            );

            $wrapper->vertexStore->removeVertex($connectedPort->id);

            foreach ($updatedTransitions as $transition) {
                $wrapper->edgeStore->addEdge($transition);
            }
        }

        return $wrapper;
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
                // Skip if transition was already removed by another port
                if (!isset($transitions[$transitionId])) {
                    continue;
                }
                $transition = $transitions[$transitionId];
                if ($transition->u()->equals($node->id())) {
                    $transitions[$transitionId] = $transition->withInput($nodeId);
                } elseif ($transition->v()->equals($node->id())) {
                    $transitions[$transitionId] = $transition->withOutput($nodeId);
                } else {
                    throw new LogicException('Port node must be either source or target of transition');
                }
            }
        }

        return [$definedNodes, $transitions];
    }
}
