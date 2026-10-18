<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\Async;

use DateTimeImmutable;
use DateTimeZone;
use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

class AsyncTimeoutFeatureTest extends TestCase
{
    #[Test]
    public function pointerLeavesThroughExpiredWhenTheResultDoesNotArriveInTime(): void
    {
        $clock = new class implements ClockInterface {
            public DateTimeImmutable $now;

            public function __construct()
            {
                $this->now = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
        $machine = new AsyncTimeoutMachine(new AsyncTimeoutContainer(), $clock);
        $execution = Execution::create();
        $execution->pointers->startAt($machine->start->id);

        $this->assertSame(ExecutionStatus::Running, $machine->execute($execution));
        $this->assertSame(ExecutionStatus::Suspended, $machine->execute($execution));

        $clock->now = $clock->now->modify('+11 seconds');
        $machine->execute($execution);

        $this->assertSame(['async-timeout.start', 'async-timeout.expired'], AsyncTimeoutVisitHandler::$visits);
    }

    protected function setUp(): void
    {
        AsyncTimeoutVisitHandler::$visits = [];
    }
}

final class AsyncTimeoutMachine extends StateMachine
{
    public readonly AsyncTimeoutVisitNode $start;

    public function __construct(ContainerInterface $container, ClockInterface $clock)
    {
        parent::__construct($container, taskBus: new DeferredTaskBus($clock));

        $this->start = new AsyncTimeoutVisitNode('async-timeout.start');
        $success = new AsyncTimeoutVisitNode('async-timeout.success');
        $fail = new AsyncTimeoutVisitNode('async-timeout.fail');
        $expired = new AsyncTimeoutVisitNode('async-timeout.expired');
        foreach ([$this->start, $success, $fail, $expired] as $node) {
            $this->addNode($node);
        }

        $async = AsyncComponent::create('timeout', static fn (States $states): Task => new AsyncTimeoutTask(), 10, $clock);
        $async->input->trigger->attach($this->start->id);
        $async->output->success->attach($success->id);
        $async->output->fail->attach($fail->id);
        $async->output->expired->attach($expired->id);
        $this->addDefinition($async);
    }
}

final class AsyncTimeoutTask implements Task
{
}

final class AsyncTimeoutVisitNode extends PassthroughNode
{
    public function handlerClass(): string
    {
        return AsyncTimeoutVisitHandler::class;
    }
}

final class AsyncTimeoutVisitHandler implements NodeHandlerInterface
{
    /** @var string[] */
    public static array $visits = [];

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        self::$visits[] = $context->node->name();

        return NodeHandlerResult::Continue;
    }
}

final class AsyncTimeoutContainer implements ContainerInterface
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
