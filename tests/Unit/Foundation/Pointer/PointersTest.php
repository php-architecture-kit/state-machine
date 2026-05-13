<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Pointer;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerCreatedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerForkedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerJoinedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerTransitionedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Join\CannotJoinPointerException;
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
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node1");
        $pointer = Pointer::create($executionId, $nodeId);

        $pointers = Pointers::recreate($executionId, null, null, null, [$pointer]);

        $this->assertArrayHasKey($pointer->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function startAtAddsPointerToCollection(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node2");
        $pointers = $this->makePointers($executionId);

        $pointer = $pointers->startAt($nodeId);

        $this->assertCount(1, $pointers->pointers);
        $this->assertArrayHasKey($pointer->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function startAtRecordsPointerCreatedEvent(): void
    {
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node3");
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
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node4");
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
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node5");
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt($nodeId);

        $pointers->remove($pointer->id);

        $this->assertEmpty($pointers->pointers);
    }

    #[Test]
    public function removeRecordsPointerRemovedEvent(): void
    {
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node6");
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
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node7");
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
    public function transitionToSingleNodeMovesPointerAndRecordsTransitionedEvent(): void
    {
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node16"));
        $pointers->releaseEvents();
        $targetNodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node17");

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
        $pointer = $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node18"));
        $pointers->releaseEvents();

        $pointers->transition($pointer->id, NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node19"), NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node20"));

        $this->assertCount(2, $pointers->pointers);
        $this->assertArrayNotHasKey($pointer->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function transitionThrowsCannotTransitionPointerExceptionForNonexistentPointer(): void
    {
        $pointers = $this->makePointers();

        $this->expectException(CannotTransitionPointerException::class);

        $pointers->transition(PointerId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node21"));
    }

    #[Test]
    public function transitionThrowsCannotTransitionPointerExceptionWhenNoTargetsProvided(): void
    {
        $pointers = $this->makePointers();
        $pointer = $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node22"));

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
            public function assertPointerTransitionAllowed(Pointer $pointer, Pointers $pointers, NodeId ...$output): void
            {
                $this->called = true;
            }
        };
        $pointers = Pointers::create($executionId, null, $policy, null);
        $pointer = $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node23"));

        $pointers->transition($pointer->id, NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node24"));

        $this->assertTrue($policyCalled);
    }

    #[Test]
    public function joinCombinesMultiplePointersIntoOne(): void
    {
        $pointers = $this->makePointers();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node25");
        $pointer1 = $pointers->startAt($nodeId);
        $pointer2 = $pointers->startAt($nodeId);
        $pointers->releaseEvents();

        $joinedPointer = $pointers->join([$pointer1->id, $pointer2->id]);

        $this->assertCount(1, $pointers->pointers);
        $this->assertArrayHasKey($joinedPointer->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function joinRemovesOriginalPointersFromCollection(): void
    {
        $pointers = $this->makePointers();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node26");
        $pointer1 = $pointers->startAt($nodeId);
        $pointer2 = $pointers->startAt($nodeId);

        $pointers->join([$pointer1->id, $pointer2->id]);

        $this->assertArrayNotHasKey($pointer1->id->toString(), $pointers->pointers);
        $this->assertArrayNotHasKey($pointer2->id->toString(), $pointers->pointers);
    }

    #[Test]
    public function joinCreatesPointerWithParentIds(): void
    {
        $pointers = $this->makePointers();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node27");
        $pointer1 = $pointers->startAt($nodeId);
        $pointer2 = $pointers->startAt($nodeId);

        $joinedPointer = $pointers->join([$pointer1->id, $pointer2->id]);

        $this->assertCount(2, $joinedPointer->parentIds);
        $this->assertTrue($pointer1->id->equals($joinedPointer->parentIds[0]));
        $this->assertTrue($pointer2->id->equals($joinedPointer->parentIds[1]));
    }

    #[Test]
    public function joinThrowsCannotJoinPointerExceptionForEmptyArray(): void
    {
        $pointers = $this->makePointers();

        $this->expectException(CannotJoinPointerException::class);

        $pointers->join([]);
    }

    #[Test]
    public function joinThrowsCannotJoinPointerExceptionForSinglePointer(): void
    {
        $pointers = $this->makePointers();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node28");
        $pointer = $pointers->startAt($nodeId);

        $this->expectException(CannotJoinPointerException::class);

        $pointers->join([$pointer->id]);
    }

    #[Test]
    public function joinThrowsCannotJoinPointerExceptionForNonexistentPointer(): void
    {
        $pointers = $this->makePointers();

        $this->expectException(CannotJoinPointerException::class);

        $pointers->join([PointerId::new(), PointerId::new()]);
    }

    #[Test]
    public function joinThrowsCannotJoinPointerExceptionWhenPointersAreInDifferentNodes(): void
    {
        $pointers = $this->makePointers();
        $nodeId1 = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node29");
        $nodeId2 = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node30");
        $pointer1 = $pointers->startAt($nodeId1);
        $pointer2 = $pointers->startAt($nodeId2);

        $this->expectException(CannotJoinPointerException::class);

        $pointers->join([$pointer1->id, $pointer2->id]);
    }

    #[Test]
    public function joinRecordsPointerJoinedEvent(): void
    {
        $pointers = $this->makePointers();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointerstest.node31");
        $pointer1 = $pointers->startAt($nodeId);
        $pointer2 = $pointers->startAt($nodeId);
        $pointers->releaseEvents();

        $joinedPointer = $pointers->join([$pointer1->id, $pointer2->id]);

        $events = $pointers->getEvents();
        $this->assertCount(3, $events);
        $this->assertInstanceOf(PointerJoinedEvent::class, $events[0]);
        $this->assertInstanceOf(PointerRemovedEvent::class, $events[1]);
        $this->assertInstanceOf(PointerRemovedEvent::class, $events[2]);
        /** @var PointerJoinedEvent $event */
        $event = $events[0];
        $this->assertTrue($joinedPointer->id->equals($event->pointerId));
        $this->assertTrue($nodeId->equals($event->nodeId));
        $this->assertCount(2, $event->parentIds);
    }
}
