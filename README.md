# php-architecture-kit/state-machine

Graph-based state machine engine for PHP. Supports parallel pointers, conditional transitions, typed state, pluggable transition strategies, and task dispatch — all built on top of `php-architecture-kit/graph`.

## Features

- **Graph-backed** — transitions are directed edges, nodes are vertices
- **Parallel pointers** — multiple execution cursors moving through the graph simultaneously
- **Conditional transitions** — guard each edge with a `TransitionCondition`
- **Typed state** — key-value `State` objects attached to each `Execution`
- **Domain events** — pointer and state lifecycle emits `DomainEvent` instances
- **Pluggable strategies** — swap transition selection and pointer scheduling strategies
- **Built-in components** — reusable higher-level building blocks for common patterns
- **PHP 8.4+** — uses asymmetric visibility and readonly classes

## Installation

```bash
composer require php-architecture-kit/state-machine
```

## Quick Start

```php
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;

// 1. Define a node
final class SendEmailNode extends Node
{
    public function handlerClass(): string
    {
        return SendEmailHandler::class;
    }
}

// 2. Implement the handler
final class SendEmailHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        // do work...
        return NodeHandlerResult::Continue;
    }
}

// 3. Define the workflow
final class OrderWorkflow extends StateMachine
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $send   = new SendEmailNode();
        $notify = new NotifyAdminNode();

        $this->addNode($send)
             ->addNode($notify)
             ->addTransition($send->id, $notify->id);
    }
}

// 4. Run
$machine   = new OrderWorkflow($container);
$execution = Execution::create();
$execution->pointers->startAt($sendNodeId);

$status = $machine->execute($execution);
// ExecutionStatus::Completed | ::Running | ::Suspended
```

## Core Concepts

### StateMachine

Extend `StateMachine` and register nodes and transitions in the constructor (or any method you choose). Call `execute(Execution $execution)` to advance all active pointers.

```php
$status = $machine->execute($execution);
```

Returns:
- `ExecutionStatus::Completed` — all pointers finished
- `ExecutionStatus::Running` — progress was made but pointers remain
- `ExecutionStatus::Suspended` — no progress, all pointers are blocked

### Node

Extend `abstract class Node` for each step in the workflow. Override `handlerClass()` to return the FQCN of the `NodeHandlerInterface` implementation resolved by the PSR-11 container.

Override `transitionStrategy()` to control how outgoing transitions are selected for that node (defaults to `AllValidTransitionsStrategy`).

### NodeHandlerInterface

```php
interface NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult;
}
```

`NodeHandlerContext` provides:
- `ExecutionId $executionId`
- `NodeInterface $node`
- `Pointer $pointer`
- `States $states`
- `dispatchTask(Task $task, array $stamps = []): void`

Return `NodeHandlerResult::Continue` to let the pointer move on, or `NodeHandlerResult::Suspended` to pause it in place without advancing.

### Transition

Directed edge between two `NodeId`s. Optionally attach a guard condition:

```php
$this->addTransition($from->id, $to->id);

// with a condition closure
$this->addTransition($from->id, $to->id, function (States $states): TransitionConditionDecision {
    return $states->getState('order') !== null
        ? TransitionConditionDecision::Accepted
        : TransitionConditionDecision::Wait;
});
```

### Execution

Holds all `Pointers` and `States` for one running instance.

```php
$execution = Execution::create();
$execution->pointers->startAt($entryNodeId);
```

### Pointer

Tracks a single cursor position (`nodeId`). Pointers can be forked for parallel branches. Retrieve events (fork, remove, etc.) via `$execution->pointers->getEvents()`.

### State

Named key-value bags attached to an execution.

```php
$execution->states->defineState('order', [
    new StateDetail('status', 'pending'),
    new StateDetail('amount', 1500),
]);

// read inside a handler or transition condition
$order = $context->states->getState('order');
$status = $order?->details['status']?->value;
```

### StateMachineConfig

Controls graph constraints and pluggable strategies:

```php
new StateMachineConfig(
    allowCycles: true,
    allowSelfLoops: true,
    allowParallelTransitions: true,
    transitionStrategies: [...],
    pointersSelectionStrategy: new AllPointersUntilBlockedStrategy(),
)
```

## Built-in Components

Components are reusable, pre-wired sub-graphs. Add them with `$machine->addDefinition($component)` and connect their ports with `addTransition()`.

### AwaitStateComponent

Suspends the pointer until a named state appears in `States`, with an optional timeout.

```php
$await = AwaitStateComponent::create('payment_result', detailName: 'status', timeout: 60);

$machine->addDefinition($await);
$machine->addTransition($prevNode->id,       $await->input->trigger);
$machine->addTransition($await->output->done,    $nextNode->id);
$machine->addTransition($await->output->expired, $timeoutNode->id);
```

### SwitchCaseComponent

Routes to the first output whose predicate returns `true` (XOR gateway).

```php
$switch = SwitchCaseComponent::create([
    'approved' => fn(States $s): bool => $s->getState('decision')?->details['value']?->value === 'approved',
    'rejected' => fn(States $s): bool => $s->getState('decision')?->details['value']?->value === 'rejected',
]);

$machine->addDefinition($switch);
$machine->addTransition($prevNode->id,           $switch->input->trigger);
$machine->addTransition($switch->output->approved, $approvedNode->id);
$machine->addTransition($switch->output->rejected, $rejectedNode->id);
```

### ParallelComponent

Spawns one pointer per branch (AND-split). Branches can be unconditional or conditionally filtered.

```php
// all branches always fire
$parallel = ParallelComponent::create(['email', 'sms', 'audit']);

// some branches are conditional
$parallel = ParallelComponent::create(
    branches:   ['email', 'sms', 'audit'],
    conditions: [
        'sms'   => fn(States $s): bool => $s->getState('user')?->details['hasSms']?->value === true,
        'audit' => fn(States $s): bool => $s->getState('order')?->details['highValue']?->value === true,
    ],
);

$machine->addDefinition($parallel);
$machine->addTransition($prevNode->id,           $parallel->input->trigger);
$machine->addTransition($parallel->output->email, $emailNode->id);
$machine->addTransition($parallel->output->sms,   $smsNode->id);
$machine->addTransition($parallel->output->audit, $auditNode->id);
```

### AwaitAllComponent

AND-join: collects all declared branch pointers before continuing.

```php
$join = AwaitAllComponent::create(['email', 'sms', 'audit']);

$machine->addDefinition($join);
$machine->addTransition($emailNode->id, $join->input->email);
$machine->addTransition($smsNode->id,   $join->input->sms);
$machine->addTransition($auditNode->id, $join->input->audit);
$machine->addTransition($join->output->done, $nextNode->id);
```

### AsyncComponent

Dispatches a `Task` exactly once, then suspends until a named state confirms completion. An `AwaitStateStamp` is automatically added to the task envelope so the handler knows which state key to write back.

```php
$async = AsyncComponent::create(
    stateName:   'payment_result',
    taskFactory: fn(States $s): Task => new ProcessPaymentTask(
        $s->getState('order')?->details['id']?->value,
    ),
    timeout: 120,
);

$machine->addDefinition($async);
$machine->addTransition($prevNode->id,      $async->input->trigger);
$machine->addTransition($async->output->done,    $nextNode->id);
$machine->addTransition($async->output->expired, $timeoutNode->id);
```

The external task handler reads `AwaitStateStamp::$stateName` from the envelope and calls:

```php
$execution->states->defineState('payment_result', [
    new StateDetail('status', 'completed'),
]);
$machine->execute($execution); // pointer resumes
```

## Built-in Transition Strategies

| Strategy | Behaviour |
|----------|-----------|
| `AllValidTransitionsStrategy` | Evaluates all outgoing transitions; passes accepted ones forward |
| `ForkTransitionStrategy` | Forks a new pointer for each accepted transition (used internally) |
| `WaitStrategy` | Suspends the pointer when all transitions return `Wait` |
| `WaitAndForkStrategy` | Waits, then forks when multiple transitions become valid |
| `RejectStrategy` | Throws when no strategy matched (safety net) |

## Built-in Pointer Selection Strategies

| Strategy | Behaviour |
|----------|-----------|
| `AllPointersUntilBlockedStrategy` | Advances every pointer until each one blocks (default) |
| `AllPointersStepStrategy` | Advances every pointer exactly one step per `execute()` call |

## License

MIT
