<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\Join;

use PhpArchitecture\StateMachine\Foundation\Component\Join\JoinComponent;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionConditionCallback;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * lap.start forks into branch.a and branch.b; each branch waits for its own state ("a", "b"),
 * then enters the join; the joined pointer reaches lap.end, which waits for "next" and starts a new lap.
 * Every event is a separate execute() on an Execution restored from its serialized form.
 */
class JoinCycleFeatureTest extends TestCase
{
    private JoinLapMachine $machine;
    private Execution $execution;

    protected function setUp(): void
    {
        JoinVisitHandler::$visits = [];
        $this->machine = new JoinLapMachine(new JoinContainer());
        $this->execution = Execution::create();
        $this->execution->pointers->startAt($this->machine->start->id);
        $this->resume();
    }

    #[Test]
    public function everyLapWaitsForBothBranches(): void
    {
        foreach (['a', 'b', 'a'] as $lap => $first) {
            $second = $first === 'a' ? 'b' : 'a';

            $this->signal($first);
            $this->assertSame($lap, $this->lapEnds(), "lap {$lap}: {$first} waits for {$second}");
            $this->assertPositions(['state-machine.join.lap.arrival.' . $first, 'join.branch.' . $second]);

            $this->signal($second);
            $this->assertSame($lap + 1, $this->lapEnds(), "lap {$lap}: joined");
            $this->assertPositions(['join.lap.end']);
            $this->assertSame([], $this->counters());

            $this->signal('next');
        }

        $this->assertPositions(['join.branch.a', 'join.branch.b']);
    }

    #[Test]
    public function secondPointerOnTheSameInputWaitsForTheNextRound(): void
    {
        // lap 2 starts while lap 1 still waits for b: two pointers will arrive on input a
        $this->signal('a');
        $this->execution->pointers->startAt($this->machine->start->id);
        $this->resume();
        $this->signal('a');
        $this->assertPositions(['state-machine.join.lap.arrival.a', 'state-machine.join.lap.arrival.a', 'join.branch.b', 'join.branch.b']);

        $this->signal('b');
        $this->assertSame(1, $this->lapEnds());
        $this->assertPositions(['state-machine.join.lap.arrival.a', 'join.branch.b', 'join.lap.end']);

        $this->signal('b');
        $this->assertSame(2, $this->lapEnds());
        $this->assertPositions(['join.lap.end', 'join.lap.end']);
        $this->assertSame([], $this->counters());
    }

    #[Test]
    public function singlePointerAtTheJoinKeepsTheExecutionSuspended(): void
    {
        $this->signal('a');

        $this->assertSame(ExecutionStatus::Suspended, $this->resume());
        $this->assertPositions(['state-machine.join.lap.arrival.a', 'join.branch.b']);
    }

    #[Test]
    public function pointerStartedBySignalJoinsLikeAnyOther(): void
    {
        $machine = new JoinLapMachine(new JoinContainer());
        $execution = Execution::create();
        $execution->pointers->startAt($machine->branchA->id);
        $this->machine = $machine;
        $this->execution = $execution;
        $this->signal('a');
        $this->assertSame(ExecutionStatus::Suspended, $this->resume());

        // the other input is fed later by an external signal
        $this->execution->pointers->startAt($machine->branchB->id);
        $this->signal('b');

        $this->assertSame(1, $this->lapEnds());
        $this->assertPositions(['join.lap.end']);
    }

    private function signal(string $state): void
    {
        $this->execution->states->defineState($state, []);
        $this->resume();
    }

    /** Restores the persisted Execution and drives it the way a runner does: while it reports progress. */
    private function resume(): ExecutionStatus
    {
        $execution = unserialize(serialize($this->execution));
        $this->assertInstanceOf(Execution::class, $execution);
        $this->execution = $execution;

        for ($round = 0; $round < 10; $round++) {
            $status = $this->machine->execute($this->execution);
            if ($status !== ExecutionStatus::Running) {
                return $status;
            }
        }

        $this->fail('The Execution keeps running.');
    }

    /** @param string[] $expected */
    private function assertPositions(array $expected): void
    {
        sort($expected);
        $this->assertSame($expected, $this->positions());
    }

    private function lapEnds(): int
    {
        return count(array_filter(JoinVisitHandler::$visits, static fn(string $name): bool => $name === 'join.lap.end'));
    }

    /** @return string[] sorted names of the nodes holding a pointer */
    private function positions(): array
    {
        $names = array_map(
            fn(Pointer $pointer): string => $this->machine->getNode($pointer->nodeId)->name(),
            array_values($this->execution->pointers->pointers),
        );
        sort($names);

        return $names;
    }

    /** @return array<string,mixed> counters the join keeps in the technical state */
    private function counters(): array
    {
        return array_filter(
            $this->execution->states->getTechnicalState()->details,
            static fn(string $name): bool => str_ends_with($name, '.waiting') || str_ends_with($name, '.consumed'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}

final class JoinLapMachine extends StateMachine
{
    public readonly JoinVisitNode $start;
    public readonly JoinVisitNode $branchA;
    public readonly JoinVisitNode $branchB;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->start = new JoinVisitNode('join.lap.start');
        $this->branchA = new JoinVisitNode('join.branch.a');
        $this->branchB = new JoinVisitNode('join.branch.b');
        $doneA = new JoinVisitNode('join.done.a');
        $doneB = new JoinVisitNode('join.done.b');
        $end = new JoinVisitNode('join.lap.end');
        foreach ([$this->start, $this->branchA, $this->branchB, $doneA, $doneB, $end] as $node) {
            $this->addNode($node);
        }

        $join = JoinComponent::create('lap', ['a', 'b']);
        $join->input->a->attach($doneA->id);
        $join->input->b->attach($doneB->id);
        $join->output->done->attach($end->id);
        $this->addDefinition($join);

        // lap.start forks into both branches
        $this->addTransition($this->start->id, $this->branchA->id);
        $this->addTransition($this->start->id, $this->branchB->id);
        $this->addTransition($this->branchA->id, $doneA->id, self::consume('a'));
        $this->addTransition($this->branchB->id, $doneB->id, self::consume('b'));
        $this->addTransition($end->id, $this->start->id, self::consume('next'));
    }

    private static function consume(string $stateName): TransitionConditionCallback
    {
        return TransitionConditionCallback::define(static function (States $states) use ($stateName): TransitionConditionDecision {
            $state = $states->getState($stateName);
            if ($state === null) {
                return TransitionConditionDecision::Wait;
            }
            $states->removeState($state->id);

            return TransitionConditionDecision::Accepted;
        });
    }
}

final class JoinVisitNode extends PassthroughNode
{
    public function handlerClass(): string
    {
        return JoinVisitHandler::class;
    }
}

final class JoinVisitHandler implements NodeHandlerInterface
{
    /** @var string[] */
    public static array $visits = [];

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        self::$visits[] = $context->node->name();

        return NodeHandlerResult::Continue;
    }
}

final class JoinContainer implements ContainerInterface
{
    public function get(string $id): object
    {
        return new $id();
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }
}
