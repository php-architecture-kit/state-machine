# php-architecture-kit/state-machine

Graph-based state machine engine for PHP. Supports parallel pointers, conditional transitions, typed state, pluggable transition strategies, and domain events — all built on top of `php-architecture-kit/graph`.

## Features

- **Graph-backed** — transitions are directed edges, nodes are vertices
- **Parallel pointers** — multiple execution cursors moving through the graph simultaneously
- **Conditional transitions** — guard each edge with a `TransitionCondition`
- **Typed state** — key-value `State` objects attached to each `Execution`
- **Domain events** — pointer and state lifecycle emits `DomainEvent` instances
- **Pluggable strategies** — swap transition selection and pointer scheduling strategies
- **PHP 8.4+** — uses asymmetric visibility and readonly classes

## Installation

```bash
composer require php-architecture-kit/state-machine
```

## Quick Start

```php
use PhpArchitecture\StateMachine\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;

// 1. Define nodes
final class SendEmail extends Node
{
    public static function new(): self
    {
        return new self(NodeId::new());
    }

    public function handlerClass(): string
    {
        return SendEmailHandler::class;
    }
}

// 2. Implement the handler
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;

final class SendEmailHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        // do work...
        return NodeHandlerResult::Completed;
    }
}

// 3. Build and run the state machine
final class OrderWorkflow extends StateMachine
{
    public function build(): static
    {
        $start  = SendEmail::new();
        $finish = NotifyAdmin::new();

        $this->addNode($start)
             ->addNode($finish)
             ->addTransition($start->id, $finish->id);

        return $this;
    }
}

$machine   = (new OrderWorkflow($container))->build();
$execution = Execution::create();
$execution->pointers->createPointer($startNodeId);

$status = $machine->execute($execution);
// ExecutionStatus::Completed | ::Running | ::Suspended
```

## Core Concepts

### StateMachine

Extend `StateMachine` to define your workflow graph. Call `addNode()` and `addTransition()` in a `build()` method, then call `execute(Execution $execution)` to advance all pointers one scheduling round.

```php
$status = $machine->execute($execution);
```

Returns `ExecutionStatus::Completed` when all pointers finish, `ExecutionStatus::Suspended` when blocked, or `ExecutionStatus::Running` when progress was made but pointers remain.

### Node

Extend `abstract class Node` for each step in the workflow. Override `handlerClass()` to return the FQCN of the `NodeHandlerInterface` implementation that the PSR-11 container will resolve.

Override `transitionStrategy()` to control how outgoing transitions are selected for that node (defaults to `AllValidTransitionsStrategy`).

### NodeHandlerInterface

```php
interface NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult;
}
```

`NodeHandlerContext` provides the `ExecutionId`, current `Node`, `Pointer`, and `States`. Return `NodeHandlerResult::Completed` to advance the pointer or `NodeHandlerResult::Suspended` to pause it.

### Transition

Directed edge between two `NodeId`s. Created via `Transition::create()`. Optionally attach a `TransitionCondition` as a guard.

```php
$this->addTransition($from->id, $to->id, new MyCondition());
```

### Execution

Holds all `Pointers` and `States` for one running instance.

```php
$execution = Execution::create();
$execution->pointers->createPointer($entryNodeId);
```

Serialise and restore with `Execution::recreate()`.

### Pointer

Tracks a single cursor's position (`nodeId`) and progress (`currentStep`). Pointers can be forked for parallel branches.

### State

Named key-value bags attached to an execution.

```php
$execution->states->define('order', [
    new StateDetail('status', 'pending'),
]);
```

Retrieve and modify state inside handlers via `$context->states`.

### StateMachineConfig

Controls graph constraints and pluggable strategies:

```php
new StateMachineConfig(
    allowCycles: true,
    allowSelfLoops: true,
    allowParallelTransitions: true,
    transitionStrategies: [
        new WaitAndForkStrategy(),
        new WaitStrategy(),
        new SingleTransitionStrategy(),
        new ForkTransitionStrategy(),
        new RejectStrategy(),
    ],
    pointersSelectionStrategy: new AllPointersUntilBlockedStrategy(),
)
```

## Built-in Transition Strategies

| Strategy | Behaviour |
|----------|-----------|
| `SingleTransitionStrategy` | Follows the one valid outgoing transition |
| `AllValidTransitionsStrategy` | Follows all valid outgoing transitions |
| `ForkTransitionStrategy` | Forks a new pointer for each valid transition |
| `WaitStrategy` | Suspends the pointer until a transition becomes valid |
| `WaitAndForkStrategy` | Waits, then forks when multiple transitions become valid |
| `RejectStrategy` | Throws when no strategy matched (safety net) |

## Built-in Pointer Selection Strategies

| Strategy | Behaviour |
|----------|-----------|
| `AllPointersUntilBlockedStrategy` | Advances every pointer until each one blocks (default) |
| `AllPointersStepStrategy` | Advances every pointer exactly one step per `execute()` call |

## License

MIT
