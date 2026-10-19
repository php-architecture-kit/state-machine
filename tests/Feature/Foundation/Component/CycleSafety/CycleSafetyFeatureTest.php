<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\CycleSafety;

use DateTimeImmutable;
use DateTimeZone;
use PhpArchitecture\Clock\ManualClock;
use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncTaskHandledNotifier;
use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Task\Attribute\HandledBy;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp\AwaitStateStamp;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\ImmediateTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Handler\TaskHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionConditionCallback;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

/**
 * Components re-entered through a cycle must behave as on their first visit,
 * also when the Execution survives between execute() calls (resumed later).
 */
class CycleSafetyFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        TrackedNodeHandler::$visits = [];
    }

    #[Test]
    public function awaitStateTimeoutIsRenewedOnEveryVisit(): void
    {
        $clock = ManualClock::at(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $machine = new AwaitLoopMachine(new CycleSafetyContainer(), $clock);
        $execution = Execution::create();
        $execution->pointers->startAt($machine->start->id);

        $machine->execute($execution);
        $this->assertSame('cycle-safety.await.door', $machine->lastVisited());

        // first lap: answered after 8 of 10 seconds, then the cycle brings the pointer back
        $clock->advanceSeconds(8);
        $execution->states->defineState('close_button', ['pressed' => true]);
        $machine->execute($execution);
        $this->assertSame('cycle-safety.await.door', $machine->lastVisited());

        // second lap: 5 seconds into a fresh 10-second window is not expired
        $clock->advanceSeconds(5);
        $machine->execute($execution);
        $this->assertSame('cycle-safety.await.door', $machine->lastVisited());

        $clock->advanceSeconds(6);
        $machine->execute($execution);
        $this->assertSame('cycle-safety.await.expired', $machine->lastVisited());
    }

    #[Test]
    public function asyncRetryLoopWaitsForTheNewResultWhenDeliveredLater(): void
    {
        $bus = new DeferredTaskBus();
        $machine = new AsyncRetryMachine(new CycleSafetyContainer(), $bus);
        $execution = Execution::create();
        $execution->pointers->startAt($machine->start->id);

        $machine->execute($execution);
        AsyncTaskHandledNotifier::fail($execution, $this->lastEnvelope($bus));
        $machine->execute($execution);
        $this->assertSame('cycle-safety.async.ask-to-fix', $machine->lastVisited());

        // user fixed the problem: check again, the new result has not arrived yet
        $execution->states->defineState('user_fixed', ['yes' => true]);
        $machine->execute($execution);
        $this->assertSame('cycle-safety.async.check', $machine->lastVisited());

        AsyncTaskHandledNotifier::success($execution, $this->lastEnvelope($bus));
        $machine->execute($execution);
        $this->assertSame('cycle-safety.async.ok', $machine->lastVisited());
    }

    #[Test]
    public function asyncIgnoresLateResultOfAPreviousVisit(): void
    {
        $bus = new DeferredTaskBus();
        $machine = new AsyncRetryMachine(new CycleSafetyContainer(), $bus);
        $execution = Execution::create();
        $execution->pointers->startAt($machine->start->id);

        $machine->execute($execution);
        $firstEnvelope = $this->lastEnvelope($bus);
        AsyncTaskHandledNotifier::fail($execution, $firstEnvelope);
        $machine->execute($execution);

        $execution->states->defineState('user_fixed', ['yes' => true]);
        $machine->execute($execution);

        // the first task reports again (duplicate / late delivery) - must not decide the second visit
        $accepted = AsyncTaskHandledNotifier::success($execution, $firstEnvelope);
        $machine->execute($execution);

        $this->assertFalse($accepted);
        $this->assertSame('cycle-safety.async.check', $machine->lastVisited());
    }

    #[Test]
    public function asyncRetryLoopWorksWithImmediateTaskBus(): void
    {
        CheckEnvironmentHandler::$results = ['fail', 'success'];
        $container = new CycleSafetyContainer();
        $execution = Execution::create();
        $machine = new AsyncRetryMachine($container, new ImmediateTaskBus($container, null, $execution));
        $execution->pointers->startAt($machine->start->id);

        $machine->execute($execution);
        $this->assertSame('cycle-safety.async.ask-to-fix', $machine->lastVisited());

        $execution->states->defineState('user_fixed', ['yes' => true]);
        $machine->execute($execution);
        $this->assertSame('cycle-safety.async.ok', $machine->lastVisited());
    }

    #[Test]
    public function acceptsResultsOfTasksSerializedBeforeVisitTokensExisted(): void
    {
        $bus = new DeferredTaskBus();
        $machine = new AsyncRetryMachine(new CycleSafetyContainer(), $bus);
        $execution = Execution::create();
        $execution->pointers->startAt($machine->start->id);
        $machine->execute($execution);

        // the envelope travelled through a queue as serialized by 1.7.1: its stamp has only "stateName"
        $envelope = $this->lastEnvelope($bus);
        $stamp = array_values(array_filter($envelope->stamps, static fn (object $stamp): bool => $stamp instanceof AwaitStateStamp))[0];
        $legacyStamp = unserialize(sprintf(
            'O:%d:"%s":1:{s:9:"stateName";s:%d:"%s";}',
            strlen(AwaitStateStamp::class),
            AwaitStateStamp::class,
            strlen($stamp->stateName),
            $stamp->stateName,
        ));
        $legacyEnvelope = TaskEnvelope::create($envelope->task, [$legacyStamp]);

        $this->assertTrue(AsyncTaskHandledNotifier::success($execution, $legacyEnvelope));
        $machine->execute($execution);
        $this->assertSame('cycle-safety.async.ok', $machine->lastVisited());
    }

    private function lastEnvelope(DeferredTaskBus $bus): TaskEnvelope
    {
        $envelopes = $bus->defferedTasks;
        $envelope = end($envelopes);
        $this->assertInstanceOf(TaskEnvelope::class, $envelope);

        return $envelope;
    }
}

final class CycleSafetyContainer implements ContainerInterface
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

abstract class CycleSafetyMachine extends StateMachine
{
    protected function node(string $name): TrackedNode
    {
        $node = new TrackedNode($name);
        $this->addNode($node);

        return $node;
    }

    /** The user node visited most recently; component internals are not tracked. */
    public function lastVisited(): ?string
    {
        $visits = TrackedNodeHandler::$visits;

        return end($visits) ?: null;
    }
}

final class TrackedNode extends PassthroughNode
{
    public function handlerClass(): string
    {
        return TrackedNodeHandler::class;
    }
}

final class TrackedNodeHandler implements NodeHandlerInterface
{
    /** @var string[] */
    public static array $visits = [];

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        self::$visits[] = $context->node->name();

        return NodeHandlerResult::Continue;
    }
}

final class AwaitLoopMachine extends CycleSafetyMachine
{
    public readonly TrackedNode $start;

    public function __construct(ContainerInterface $container, ClockInterface $clock)
    {
        parent::__construct($container);

        $this->start = $this->node('cycle-safety.await.start');
        $door = $this->node('cycle-safety.await.door');
        $closing = $this->node('cycle-safety.await.closing');
        $expired = $this->node('cycle-safety.await.expired');

        $await = AwaitStateComponent::create('door', 'close_button', timeout: 10, clock: $clock);
        $await->input->at->attach($door->id);
        $await->output->run->attach($closing->id);
        $await->output->expired->attach($expired->id);

        $this->addTransition($this->start->id, $door->id);
        $this->addDefinition($await);
        // closing consumes the button press and opens the door again (cycle)
        $this->addTransition($closing->id, $door->id, TransitionConditionCallback::define(
            static function (States $states): TransitionConditionDecision {
                $button = $states->getState('close_button');
                if ($button !== null) {
                    $states->removeState($button->id);
                }

                return TransitionConditionDecision::Accepted;
            },
        ));
    }
}

final class AsyncRetryMachine extends CycleSafetyMachine
{
    public readonly TrackedNode $start;

    public function __construct(ContainerInterface $container, TaskBusInterface $taskBus)
    {
        parent::__construct($container, taskBus: $taskBus);

        $this->start = $this->node('cycle-safety.async.start');
        $check = $this->node('cycle-safety.async.check');
        $askToFix = $this->node('cycle-safety.async.ask-to-fix');
        $ok = $this->node('cycle-safety.async.ok');

        $async = AsyncComponent::create('check-environment', static fn(States $states): Task => new CheckEnvironment());
        $async->input->trigger->attach($check->id);
        $async->output->success->attach($ok->id);
        $async->output->fail->attach($askToFix->id);
        $async->output->expired->attach($askToFix->id);

        $this->addTransition($this->start->id, $check->id);
        $this->addDefinition($async);
        $this->addTransition($askToFix->id, $check->id, TransitionConditionCallback::define(
            static function (States $states): TransitionConditionDecision {
                $fixed = $states->getState('user_fixed');
                if ($fixed === null) {
                    return TransitionConditionDecision::Wait;
                }
                $states->removeState($fixed->id);

                return TransitionConditionDecision::Accepted;
            },
        ));
    }

}

#[HandledBy(CheckEnvironmentHandler::class)]
final class CheckEnvironment implements Task
{
}

final class CheckEnvironmentHandler implements TaskHandlerInterface
{
    /** @var string[] */
    public static array $results = [];

    public function handle(TaskEnvelope $envelope, ?Execution $execution = null): void
    {
        assert($execution !== null);
        $result = array_shift(self::$results) ?? 'success';

        $result === 'success'
            ? AsyncTaskHandledNotifier::success($execution, $envelope)
            : AsyncTaskHandledNotifier::fail($execution, $envelope);
    }
}
