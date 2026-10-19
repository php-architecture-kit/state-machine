<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PhpArchitecture\StateMachine\Foundation\Config\StateMachineConfig;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Config\Exception\NoTransitionStrategyException;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Definition\DefinitionCompiler;
use PhpArchitecture\StateMachine\Foundation\Exception\Modification\CannotAddNodeDuringExecutionException;
use PhpArchitecture\StateMachine\Foundation\Exception\Modification\CannotAddTransitionDuringExecutionException;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeHandlerException;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\NodeNotFoundException;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Pointer\NodeHandlingStatus;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use Psr\Container\ContainerInterface;
use ReflectionClass;

abstract class StateMachine
{
    protected readonly Graph $graph;
    protected bool $isAnyExecutionRunning = false;

    /** @var array<string,NodeHandlerInterface> built-in handlers created because the container lacks them */
    private array $builtInHandlers = [];

    public function __construct(
        protected readonly ContainerInterface $container,
        ?Graph $graph = null,
        protected readonly StateMachineConfig $config = new StateMachineConfig(),
        protected readonly TaskBusInterface $taskBus = new DeferredTaskBus(),
    ) {
        $this->graph = $graph ?? new Graph($this->config->toGraphConfig());
    }

    public function addDefinition(Definition $definition): static
    {
        [$nodes, $transitions] = (new DefinitionCompiler)->compile($definition);
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
        foreach ($transitions as $transition) {
            $this->addTransition($transition->input, $transition->output, $transition->condition); // @phpstan-ignore-line
        }

        return $this;
    }

    protected function addNode(NodeInterface $node): static
    {
        if ($this->isAnyExecutionRunning) {
            throw new CannotAddNodeDuringExecutionException('Cannot add node during execution');
        }

        $this->graph->vertexStore->addVertex($node);

        return $this;
    }

    public function addTransition(NodeId $input, NodeId $output, ?TransitionCondition $condition = null): static
    {
        if ($this->isAnyExecutionRunning) {
            throw new CannotAddTransitionDuringExecutionException('Cannot add transition during execution');
        }

        $this->graph->edgeStore->addEdge(Transition::create($input, $output, $condition));

        return $this;
    }

    public function execute(Execution $execution, ?int $maxIterations = null): ExecutionStatus
    {
        $iterations = 0;
        $anyProgress = false;
        $this->isAnyExecutionRunning = true;
        do {
            $plans = $this->config->pointersSelectionStrategy->select($execution->pointers);
            $madeProgress = false;
            foreach ($plans as $plan) {
                $stepBefore = $plan->pointer->currentStep;
                $wasPending = $plan->pointer->handlingStatus === NodeHandlingStatus::Pending;
                $this->handlePointerOnPath($plan->pointer, $execution, $plan->maxSteps);

                $pointerRemoved = !isset($execution->pointers->pointers[$plan->pointer->id->toString()]);
                $progressed = $pointerRemoved || $plan->pointer->currentStep > $stepBefore;
                if ($progressed) {
                    $anyProgress = true;
                }
                if ($progressed && $wasPending) {
                    $madeProgress = true;
                }
            }
            $iterations++;
        } while ($madeProgress === true && ($maxIterations === null || $iterations < $maxIterations));

        $this->isAnyExecutionRunning = false;
        if (empty($execution->pointers->pointers)) {
            return ExecutionStatus::Completed;
        }

        return $anyProgress ? ExecutionStatus::Running : ExecutionStatus::Suspended;
    }

    protected function handlePointerOnPath(Pointer $pointer, Execution $execution, int $maxSteps): void
    {
        for ($i = 0; $i < $maxSteps; $i++) {
            if (!isset($execution->pointers->pointers[$pointer->id->toString()])) {
                break;
            }

            $stepBeforeHandling = $pointer->currentStep;
            $result = $this->handlePointerOnNode($pointer, $execution);

            if ($pointer->currentStep === $stepBeforeHandling) {
                break;
            }

            if ($result === NodeHandlerResult::Suspended) {
                break;
            }
        }
    }

    protected function handlePointerOnNode(Pointer $pointer, Execution $execution): NodeHandlerResult
    {
        $node = $this->getNode($pointer->nodeId);
        $handler = $this->resolveHandler($node->handlerClass());

        if (!$handler instanceof NodeHandlerInterface) {
            throw new InvalidNodeHandlerException(
                "Handler for node '{$pointer->nodeId}' must implement NodeHandlerInterface, got " . get_class($handler) . ".",
            );
        }

        if ($pointer->handlingStatus === NodeHandlingStatus::Completed) {
            $this->transitionToNextNodes($pointer, $execution);
            return NodeHandlerResult::Continue;
        }

        $handlerResult = $handler->handle(
            new NodeHandlerContext($execution->id, $node, $pointer, $execution->states, $this->taskBus),
        );

        if ($handlerResult === NodeHandlerResult::Suspended) {
            return $handlerResult;
        }

        $pointer->markNodeHandlingStatusCompleted();
        $this->transitionToNextNodes($pointer, $execution);
        return $handlerResult;
    }

    /**
     * Handlers come from the container. Handlers of the package's own nodes and components
     * have no dependencies, so when the container does not provide them they are created directly.
     */
    private function resolveHandler(string $handlerClass): mixed
    {
        if (isset($this->builtInHandlers[$handlerClass])) {
            return $this->builtInHandlers[$handlerClass];
        }

        if (
            !$this->container->has($handlerClass)
            && str_starts_with($handlerClass, __NAMESPACE__ . '\\')
            && is_subclass_of($handlerClass, NodeHandlerInterface::class)
        ) {
            $class = new ReflectionClass($handlerClass);
            if ($class->isInstantiable() && ($class->getConstructor()?->getNumberOfRequiredParameters() ?? 0) === 0) {
                return $this->builtInHandlers[$handlerClass] = $class->newInstance();
            }
        }

        return $this->container->get($handlerClass);
    }

    protected function transitionToNextNodes(Pointer $pointer, Execution $execution): TransitionSelectionOutput
    {
        $node = $this->getNode($pointer->nodeId);
        $outgoing = $this->getOutgoingTransitions($node->id());

        $transitionSelection = $node->transitionStrategy()->select($pointer, $execution->states, $outgoing);

        foreach ($this->config->transitionStrategies as $strategy) {
            if ($strategy->supports($transitionSelection)) {
                $strategy->transitionToNextNodes($execution, $pointer, $transitionSelection);
                return $transitionSelection;
            }
        }

        throw new NoTransitionStrategyException(
            "No TransitionStrategy supports the current transition output for node '{$pointer->nodeId}'. Check StateMachineConfig::transitionStrategies.",
        );
    }

    /**
     * @throws NodeNotFoundException when the definition has no node with this id
     */
    public function getNode(NodeId $id): NodeInterface
    {
        $node = $this->graph->vertexStore->getVertex($id);
        if (!$node instanceof NodeInterface) {
            throw new NodeNotFoundException("Node '{$id}' not found in the graph.");
        }

        return $node;
    }

    public function hasNode(NodeId $id): bool
    {
        return $this->graph->vertexStore->getVertex($id) instanceof NodeInterface;
    }

    /**
     * @return array<string,NodeInterface> keyed by node id
     */
    public function getNodes(): array
    {
        /** @var array<string,NodeInterface> $nodes */
        $nodes = $this->graph->vertexStore->getVertices(static fn(VertexInterface $vertex): bool => $vertex instanceof NodeInterface);
        return $nodes;
    }

    /**
     * @return Transition[]
     */
    public function getTransitions(): array
    {
        /** @var Transition[] $transitions */
        $transitions = array_values($this->graph->edgeStore->getEdges(static fn(EdgeInterface $edge): bool => $edge instanceof Transition));
        return $transitions;
    }

    /**
     * @return Transition[]
     */
    protected function getOutgoingTransitions(NodeId $id): array
    {
        /** @var Transition[] $edges */
        $edges = array_values($this->graph->edgeStore->getIncidentEdges(
            $id,
            static fn(EdgeInterface $edge): bool => $edge->u()->equals($id),
        ));
        return $edges;
    }
}
