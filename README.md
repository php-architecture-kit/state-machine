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
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use Psr\Container\ContainerInterface;

// 1. Define a node - its globally unique name ({vendor}.{module}.{purpose}) is also its identity
final class SendEmailNode extends Node
{
    public function handlerClass(): string
    {
        return SendEmailHandler::class;
    }
}

// 2. Implement the handler (resolved from the PSR-11 container)
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
    public readonly SendEmailNode $send;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->send = new SendEmailNode('acme.orders.send-email');
        $notify     = new NotifyAdminNode('acme.orders.notify-admin');

        $this->addNode($this->send)
             ->addNode($notify)
             ->addTransition($this->send->id, $notify->id);
    }
}

// 4. Run
$machine   = new OrderWorkflow($container);
$execution = Execution::create();
$execution->pointers->startAt($machine->send->id);

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

Pointers, pointer events and technical state keys carry only `NodeId`s. The definition can be read
back to turn them into nodes, for example in a log or audit trail:

```php
$machine->getNode($pointer->nodeId)->name(); // throws NodeNotFoundException for an unknown id
$machine->hasNode($nodeId);
$machine->getNodes();       // array<string,NodeInterface> keyed by node id
$machine->getTransitions(); // Transition[]
```

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

// with a condition (any TransitionCondition; TransitionConditionCallback wraps a closure)
$this->addTransition($from->id, $to->id, TransitionConditionCallback::define(
    fn(States $states): TransitionConditionDecision => $states->getState('order') !== null
        ? TransitionConditionDecision::Accepted
        : TransitionConditionDecision::Wait,
));
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

Components are reusable, pre-wired sub-graphs (`Definition`) with named input and output ports.
Connect the ports to your nodes with `attach()` **before** adding the component with
`$machine->addDefinition($component)` - the compiler replaces every port with the node attached to it.

```php
$component->input->trigger->attach($previousNode->id);   // previous node -> component
$component->output->done->attach($nextNode->id);         // component -> next node
$machine->addDefinition($component);
```

Components use the package's own node handlers (`PassthroughNodeHandler`, `AwaitStateNodeHandler`,
`CreateAsyncTaskNodeHandler`, `AwaitAllArrivalNodeHandler`). They have no dependencies: when your
container does not provide them, the machine creates them itself.

### AwaitStateComponent

Waits until a named state (optionally with a given detail) appears in `States`, with an optional timeout.
Ports: input `at`, outputs `run` and `expired`. The timeout window starts on every visit, so the
component can be re-entered through a cycle. `expired` becomes available once exactly `timeout`
seconds have passed since the visit (`now >= visit + timeout`).

```php
$await = AwaitStateComponent::create('payment', 'payment_result', detailName: 'status', timeout: 60, clock: $clock);

$await->input->at->attach($prevNode->id);
$await->output->run->attach($nextNode->id);
$await->output->expired->attach($timeoutNode->id);
$machine->addDefinition($await);
```

### ChoiceComponent

Routes to the first output whose predicate accepts (XOR gateway). Port: input `trigger`; one output per branch.

```php
$choice = ChoiceComponent::create('decision', [
    'approved' => fn(States $s): TransitionConditionDecision => $s->getState('decision')?->details['value']?->value === 'approved'
        ? TransitionConditionDecision::Accepted
        : TransitionConditionDecision::Rejected,
    'rejected' => null, // no predicate - always matches, so keep it last
]);

$choice->input->trigger->attach($prevNode->id);
$choice->output->approved->attach($approvedNode->id);
$choice->output->rejected->attach($rejectedNode->id);
$machine->addDefinition($choice);
```

### ForkComponent

Spawns one pointer per branch (AND-split). Branches without a predicate always fire.
Port: input `trigger`; one output per branch.

```php
$fork = ForkComponent::create('notify', ['email', 'sms', 'audit'], [
    'sms' => fn(States $s): TransitionConditionDecision => $s->getState('user')?->details['hasSms']?->value === true
        ? TransitionConditionDecision::Accepted
        : TransitionConditionDecision::Rejected,
]);

$fork->input->trigger->attach($prevNode->id);
$fork->output->email->attach($emailNode->id);
$fork->output->sms->attach($smsNode->id);
$fork->output->audit->attach($auditNode->id);
$machine->addDefinition($fork);
```

### AwaitAllComponent

AND-join: waits for every declared branch before continuing. Ports: one input per branch, output `done`.

```php
$join = AwaitAllComponent::create('notify', ['email', 'sms', 'audit']);

$join->input->email->attach($emailNode->id);
$join->input->sms->attach($smsNode->id);
$join->input->audit->attach($auditNode->id);
$join->output->done->attach($nextNode->id);
$machine->addDefinition($join);
```

### RaceComponent

The first pointer to arrive continues, one pointer from every other input loses and is removed.
Ports: one input per declared input (at least two), output `winner`.

```php
$race = RaceComponent::create('reply', ['answer', 'timeout']);

$race->input->answer->attach($answeredNode->id);
$race->input->timeout->attach($timedOutNode->id);
$race->output->winner->attach($nextNode->id);
$machine->addDefinition($race);
```

A round is one arrival per input. The winner leaves at once, and every other input owes one loser:
the next pointer arriving there is removed. All pointers are treated alike, whether they come from a
fork or were started by a signal. The counters live in the technical state of the `Execution` and drop
back to zero once all losers have arrived, so the race can be re-entered through a cycle and the
`Execution` can be persisted at any point. A loser that never reaches the race is never removed and
keeps the `Execution` from completing.

### RaceFirstComponent (deprecated)

Deprecated since 1.10.0, removed in 2.0 - use `RaceComponent`. The first pointer to arrive continues,
every later one is terminated. Ports: input `gateway`, output `winner`. The winner is remembered for the
lifetime of the `Execution`, so the component does not work when re-entered through a cycle.

**Migrating to `RaceComponent`.** Give each competing branch its own input instead of attaching all of
them to `gateway`:

```php
// before
$race = RaceFirstComponent::create('reply');
$race->input->gateway->attach($answeredNode->id);
$race->input->gateway->attach($timedOutNode->id);

// after
$race = RaceComponent::create('reply', ['answer', 'timeout']);
$race->input->answer->attach($answeredNode->id);
$race->input->timeout->attach($timedOutNode->id);
```

The two components keep their state under different nodes. Deploy the change when no persisted
`Execution` has an open round of this race (a winner has passed but a loser is still on its way);
otherwise the new component does not know about that loser and lets it win.

### AsyncComponent

Dispatches a `Task` once per visit, then waits until the task handler reports the result.
Ports: input `trigger`, outputs `success`, `fail` and `expired`. `expired` follows the same rule as in
`AwaitStateComponent`: available once exactly `timeout` seconds have passed since the visit.

```php
$async = AsyncComponent::create(
    'payment',
    fn(States $s): Task => new ProcessPaymentTask($s->getState('order')?->details['id']?->value),
    timeout: 120,
);

$async->input->trigger->attach($prevNode->id);
$async->output->success->attach($paidNode->id);
$async->output->fail->attach($declinedNode->id);
$async->output->expired->attach($timeoutNode->id);
$machine->addDefinition($async);
```

The task envelope carries an `AwaitStateStamp`. The task handler - in the same process
(`ImmediateTaskBus`) or later, after the `Execution` was persisted (`DeferredTaskBus`) - reports
the result and the machine is driven again:

```php
AsyncTaskHandledNotifier::success($execution, $envelope); // or ::fail()
$machine->execute($execution); // pointer resumes
```

A result of a task dispatched by an earlier visit of the same component (a late or duplicated
delivery after a cycle) is ignored - `notify()` returns `false` then.

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
