<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Pointer;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerCreatedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerForkedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerTransitionedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Removal\CannotRemovePointerException;
use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Transition\CannotTransitionPointerException;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PointersTest extends TestCase
{
    private function makePointers(?ExecutionId $executionId = null): Pointers
    {
        return Pointers::create($executionId ?? ExecutionId::new(), null, null, null);
    }

    #[Test]
    public function createReturnsEmptyPointers(): void
    {
        $pointers = $this->makePointers();

        $this->assertEmpty($pointers->pointers);
    }

    #[Test]
    public function createStoresExecutionId(): void
    {
        $executionId = ExecutionId::new();
        $pointers = $this->makePointers($executionId);

        $this->assertTrue($executionId->equals($pointers->executionId));
    }

    #[Test]
    public function recreateIndexesPointersByStringId(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $pointer = Pointer::create($executionId, $nodeId);

        $pointers = Pointers::recreate($executionId, null, null, null, [$pointer]);

        $this->assertArrayHasKey($pointer->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function startAtAddsPointerToCollection(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $pointers = $this->makePointers($executionId);

        $pointer = $pointers->startAt($nodeId);

        $this->assertCount(1, $pointers->pointers);
        $this->assertArrayHasKey($pointer->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function startAtRecordsPointerCreatedEvent(): void
    {
        $nodeId = NodeId::new();
        $pointers = $this->makePointers();

        $pointers->startAt($nodeId);

        $events = $pointers->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PointerCreatedEvent::class, $events[0]);
    }

    #[Test]
    public function startAtCallsCreationPolicyWhenSet(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $policyCalled = false;
        $policy = new class($policyCalled) implements \PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerCreationPolicy {
            public function __construct(private bool &$called) {}
            public function assertPointerCreationAllowed(Pointer $pointer, Pointers $pointers): void
            {
                $this->called = true;
            }
        };
        $pointers = Pointers::create($executionId, $policy, null, null);

        $pointers->startAt($nodeId);

        $this->assertTrue($policyCalled);
    }

    #[Test]
    public function removeDeletesPointerFromCollection(): void
    {
        $nodeId = NodeId::new();
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt($nodeId);

        $pointers->remove($pointer->id);

        $this->assertEmpty($pointers->pointers);
    }

    #[Test]
    public function removeRecordsPointerRemovedEvent(): void
    {
        $nodeId = NodeId::new();
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt($nodeId);
        $pointers->releaseEvents();

        $pointers->remove($pointer->id);

        $events = $pointers->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PointerRemovedEvent::class, $events[0]);
    }

    #[Test]
    public function removeThrowsCannotRemovePointerExceptionForNonexistentPointer(): void
    {
        $pointers = $this->makePointers();

        $this->expectException(CannotRemovePointerException::class);

        $pointers->remove(PointerId::new());
    }

    #[Test]
    public function removeCallsRemovalPolicyWhenSet(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $policyCalled = false;
        $policy = new class($policyCalled) implements \PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerRemovalPolicy {
            public function __construct(private bool &$called) {}
            public function assertPointerRemovalAllowed(Pointer $pointer, Pointers $pointers): void
            {
                $this->called = true;
            }
        };
        $pointers = Pointers::create($executionId, null, null, $policy);
        $pointer = $pointers->startAt($nodeId);

        $pointers->remove($pointer->id);

        $this->assertTrue($policyCalled);
    }

    #[Test]
    public function forkToCreatesForkedPointersAndKeepsOriginal(): void
    {
        $nodeId = NodeId::new();
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt($nodeId);
        $pointers->releaseEvents();

        $target1 = NodeId::new();
        $target2 = NodeId::new();
        $pointers->forkTo($pointer->id, $target1, $target2);

        $this->assertCount(3, $pointers->pointers);
    }

    #[Test]
    public function forkToRecordsForkedAndTransitionedEvents(): void
    {
        $nodeId = NodeId::new();
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt($nodeId);
        $pointers->releaseEvents();

        $pointers->forkTo($pointer->id, NodeId::new(), NodeId::new());

        $events = $pointers->getEvents();
        $forkedEvents = array_filter($events, static fn($e) => $e instanceof PointerForkedEvent);
        $transitionedEvents = array_filter($events, static fn($e) => $e instanceof PointerTransitionedEvent);

        $this->assertCount(2, $forkedEvents);
        $this->assertCount(2, $transitionedEvents);
    }

    #[Test]
    public function forkToThrowsCannotTransitionPointerExceptionForNonexistentPointer(): void
    {
        $pointers = $this->makePointers();

        $this->expectException(CannotTransitionPointerException::class);

        $pointers->forkTo(PointerId::new(), NodeId::new());
    }

    #[Test]
    public function forkToThrowsCannotTransitionPointerExceptionWhenNoTargetsProvided(): void
    {
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt(NodeId::new());

        $this->expectException(CannotTransitionPointerException::class);

        $pointers->forkTo($pointer->id);
    }

    #[Test]
    public function transitionToSingleNodeMovesPointerAndRecordsTransitionedEvent(): void
    {
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt(NodeId::new());
        $pointers->releaseEvents();
        $targetNodeId = NodeId::new();

        $pointers->transition($pointer->id, $targetNodeId);

        $this->assertCount(1, $pointers->pointers);
        $this->assertTrue($targetNodeId->equals($pointer->nodeId));

        $events = $pointers->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PointerTransitionedEvent::class, $events[0]);
    }

    #[Test]
    public function transitionToMultipleNodesForksAndRemovesOriginal(): void
    {
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt(NodeId::new());
        $pointers->releaseEvents();

        $pointers->transition($pointer->id, NodeId::new(), NodeId::new());

        $this->assertCount(2, $pointers->pointers);
        $this->assertArrayNotHasKey($pointer->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function transitionThrowsCannotTransitionPointerExceptionForNonexistentPointer(): void
    {
        $pointers = $this->makePointers();

        $this->expectException(CannotTransitionPointerException::class);

        $pointers->transition(PointerId::new(), NodeId::new());
    }

    #[Test]
    public function transitionThrowsCannotTransitionPointerExceptionWhenNoTargetsProvided(): void
    {
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt(NodeId::new());

        $this->expectException(CannotTransitionPointerException::class);

        $pointers->transition($pointer->id);
    }

    #[Test]
    public function transitionCallsTransitionPolicyWhenSet(): void
    {
        $executionId = ExecutionId::new();
        $policyCalled = false;
        $policy = new class($policyCalled) implements \PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerTransitionPolicy {
            public function __construct(private bool &$called) {}
            public function assertPointerTransitionAllowed(Pointer $pointer, Pointers $pointers, NodeId ...$to): void
            {
                $this->called = true;
            }
        };
        $pointers = Pointers::create($executionId, null, $policy, null);
        $pointer = $pointers->startAt(NodeId::new());

        $pointers->transition($pointer->id, NodeId::new());

        $this->assertTrue($policyCalled);
    }
}
