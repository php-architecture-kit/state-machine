<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\Race;

use PhpArchitecture\StateMachine\Foundation\Component\Race\RaceComponent;
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
 * then enters the race; the winner reaches lap.end, which waits for "next" and starts a new lap.
 * Every event is a separate execute() on an Execution restored from its serialized form.
 */
class RaceCycleFeatureTest extends TestCase
{
    private RaceLapMachine $machine;
    private Execution $execution;

    protected function setUp(): void
    {
        RaceVisitHandler::$visits = [];
        $this->machine = new RaceLapMachine(new RaceContainer());
        $this->execution = Execution::create();
        $this->execution->pointers->startAt($this->machine->start->id);
        $this->resume();
    }

    #[Test]
    public function everyLapHasExactlyOneWinner(): void
    {
        foreach (['a', 'b', 'a'] as $lap => $first) {
            $second = $first === 'a' ? 'b' : 'a';

            $this->signal($first);
            $this->assertSame($lap + 1, $this->lapEnds(), "lap {$lap}: {$first} wins");

            $this->signal($second);
            $this->assertSame($lap + 1, $this->lapEnds(), "lap {$lap}: {$second} loses");
            $this->assertSame(['race.lap.end'], $this->positions());
            $this->assertSame([], $this->counters());

            $this->signal('next');
        }

        $this->assertSame(['race.branch.a', 'race.branch.b'], $this->positions());
    }

    #[Test]
    public function lateLoserOfThePreviousLapDoesNotWinTheNextOne(): void
    {
        $this->signal('a');
        $this->signal('next');
        $this->assertSame(['race.branch.a', 'race.branch.b', 'race.branch.b'], $this->positions());

        // the loser of lap 1 arrives only now, during lap 2
        $this->signal('b');
        $this->assertSame(1, $this->lapEnds());
        $this->assertSame(['race.branch.a', 'race.branch.b'], $this->positions());

        $this->signal('a');
        $this->assertSame(2, $this->lapEnds());

        $this->signal('b');
        $this->assertSame(['race.lap.end'], $this->positions());
        $this->assertSame([], $this->counters());
    }

    #[Test]
    public function fastBranchWinsTwoLapsInARowAndBothLosersAreAbsorbed(): void
    {
        $this->signal('a');
        $this->signal('next');
        $this->signal('a');
        $this->assertSame(2, $this->lapEnds());
        $this->assertSame(['race.branch.b', 'race.branch.b', 'race.lap.end'], $this->positions());

        $this->signal('b');
        $this->signal('b');
        $this->assertSame(2, $this->lapEnds());
        $this->assertSame(['race.lap.end'], $this->positions());
        $this->assertSame([], $this->counters());
    }

    #[Test]
    public function pointerStartedBySignalCompetesLikeAnyOther(): void
    {
        $machine = new RaceLapMachine(new RaceContainer());
        $execution = Execution::create();
        $execution->pointers->startAt($machine->branchA->id);
        $execution->pointers->startAt($machine->branchB->id);
        $this->machine = $machine;
        $this->execution = $execution;
        $this->resume();

        $this->signal('b');
        $this->signal('a');

        $this->assertSame(1, $this->lapEnds());
        $this->assertSame(['race.lap.end'], $this->positions());
        $this->assertSame(ExecutionStatus::Suspended, $this->resume());
    }

    private function signal(string $state): void
    {
        $this->execution->states->defineState($state, []);
        $this->resume();
    }

    private function resume(): ExecutionStatus
    {
        $execution = unserialize(serialize($this->execution));
        $this->assertInstanceOf(Execution::class, $execution);
        $this->execution = $execution;

        return $this->machine->execute($this->execution);
    }

    private function lapEnds(): int
    {
        return count(array_filter(RaceVisitHandler::$visits, static fn(string $name): bool => $name === 'race.lap.end'));
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

    /** @return array<string,mixed> counters the race keeps in the technical state */
    private function counters(): array
    {
        return array_filter(
            $this->execution->states->getTechnicalState()->details,
            static fn(string $name): bool => str_ends_with($name, '.losers'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}

final class RaceLapMachine extends StateMachine
{
    public readonly RaceVisitNode $start;
    public readonly RaceVisitNode $branchA;
    public readonly RaceVisitNode $branchB;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->start = new RaceVisitNode('race.lap.start');
        $this->branchA = new RaceVisitNode('race.branch.a');
        $this->branchB = new RaceVisitNode('race.branch.b');
        $doneA = new RaceVisitNode('race.done.a');
        $doneB = new RaceVisitNode('race.done.b');
        $end = new RaceVisitNode('race.lap.end');
        foreach ([$this->start, $this->branchA, $this->branchB, $doneA, $doneB, $end] as $node) {
            $this->addNode($node);
        }

        $race = RaceComponent::create('lap', ['a', 'b']);
        $race->input->a->attach($doneA->id);
        $race->input->b->attach($doneB->id);
        $race->output->winner->attach($end->id);
        $this->addDefinition($race);

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

final class RaceVisitNode extends PassthroughNode
{
    public function handlerClass(): string
    {
        return RaceVisitHandler::class;
    }
}

final class RaceVisitHandler implements NodeHandlerInterface
{
    /** @var string[] */
    public static array $visits = [];

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        self::$visits[] = $context->node->name();

        return NodeHandlerResult::Continue;
    }
}

final class RaceContainer implements ContainerInterface
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
