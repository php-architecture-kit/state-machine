<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\AwaitState\Node;

use DateTimeImmutable;
use PhpArchitecture\Clock\ManualClock;
use PhpArchitecture\StateMachine\Foundation\Component\Await\Node\AwaitStateNode;
use PhpArchitecture\StateMachine\Foundation\Component\Await\Node\AwaitStateNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AwaitStateNodeHandlerTest extends TestCase
{
    #[Test]
    public function handleReturnsContinue(): void
    {
        $handler = new PassthroughNodeHandler();
        $context = $this->createStub(NodeHandlerContext::class);

        $result = $handler->handle($context);

        $this->assertSame(NodeHandlerResult::Continue, $result);
    }

    #[Test]
    public function everyVisitIssuesAFreshExpirationMoment(): void
    {
        $clock = ManualClock::at(new DateTimeImmutable('2026-01-01 12:00:00'));

        $node = new AwaitStateNode('state-machine.test.await-node', 30, $clock);
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt($node->id);
        $context = new NodeHandlerContext($execution->id, $node, $pointer, $execution->states, new DeferredTaskBus());
        $handler = new AwaitStateNodeHandler();

        $this->assertSame(NodeHandlerResult::Continue, $handler->handle($context));
        $this->assertEquals(
            new DateTimeImmutable('2026-01-01 12:00:30'),
            $execution->states->getTechnicalState()[$node->expiresAtStateName()]?->value,
        );

        $clock->set(new DateTimeImmutable('2026-01-01 12:05:00'));
        $handler->handle($context);

        $this->assertEquals(
            new DateTimeImmutable('2026-01-01 12:05:30'),
            $execution->states->getTechnicalState()[$node->expiresAtStateName()]?->value,
        );
    }
}
