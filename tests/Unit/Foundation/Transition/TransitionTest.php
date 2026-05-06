<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition;

use PhpArchitecture\Graph\Edge\EdgeType;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Exception\InvalidTransitionException;
use PhpArchitecture\StateMachine\Foundation\Transition\Identity\TransitionId;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransitionTest extends TestCase
{
    #[Test]
    public function createGeneratesNonNullId(): void
    {
        $transition = Transition::create(NodeId::new(), NodeId::new());

        $this->assertInstanceOf(TransitionId::class, $transition->id);
    }

    #[Test]
    public function createReturnsDifferentIdOnEachCall(): void
    {
        $from = NodeId::new();
        $to = NodeId::new();

        $a = Transition::create($from, $to);
        $b = Transition::create($from, $to);

        $this->assertFalse($a->id->equals($b->id));
    }

    #[Test]
    public function createStoresFromAndToNodes(): void
    {
        $from = NodeId::new();
        $to = NodeId::new();

        $transition = Transition::create($from, $to);

        $this->assertTrue($from->equals($transition->from));
        $this->assertTrue($to->equals($transition->to));
    }

    #[Test]
    public function createStoresNullConditionByDefault(): void
    {
        $transition = Transition::create(NodeId::new(), NodeId::new());

        $this->assertNull($transition->condition);
    }

    #[Test]
    public function createStoresEmptyTagsByDefault(): void
    {
        $transition = Transition::create(NodeId::new(), NodeId::new());

        $this->assertSame([], $transition->tags);
    }

    #[Test]
    public function createStoresTags(): void
    {
        $transition = Transition::create(NodeId::new(), NodeId::new(), null, ['priority', 'fast']);

        $this->assertSame(['priority', 'fast'], $transition->tags);
    }

    #[Test]
    public function constructorThrowsInvalidTransitionExceptionOnNonStringTag(): void
    {
        $this->expectException(InvalidTransitionException::class);

        Transition::create(NodeId::new(), NodeId::new(), null, [42]);
    }

    #[Test]
    public function uReturnsFromNode(): void
    {
        $from = NodeId::new();

        $transition = Transition::create($from, NodeId::new());

        $this->assertTrue($from->equals($transition->u()));
    }

    #[Test]
    public function vReturnsToNode(): void
    {
        $to = NodeId::new();

        $transition = Transition::create(NodeId::new(), $to);

        $this->assertTrue($to->equals($transition->v()));
    }

    #[Test]
    public function tagsReturnsProvidedTags(): void
    {
        $transition = Transition::create(NodeId::new(), NodeId::new(), null, ['tag1']);

        $this->assertSame(['tag1'], $transition->tags());
    }

    #[Test]
    public function typeReturnsDirectedEdgeType(): void
    {
        $transition = Transition::create(NodeId::new(), NodeId::new());

        $this->assertSame(EdgeType::Directed, $transition->type());
    }

    #[Test]
    public function idMethodReturnsTransitionId(): void
    {
        $transition = Transition::create(NodeId::new(), NodeId::new());

        $this->assertSame($transition->id, $transition->id());
    }

    #[Test]
    public function recreateUsesProvidedId(): void
    {
        $id = TransitionId::new();
        $from = NodeId::new();
        $to = NodeId::new();

        $transition = Transition::recreate($id, $from, $to, null, []);

        $this->assertTrue($id->equals($transition->id));
    }

    #[Test]
    public function recreatePreservesFromToAndTags(): void
    {
        $from = NodeId::new();
        $to = NodeId::new();

        $transition = Transition::recreate(TransitionId::new(), $from, $to, null, ['tag']);

        $this->assertTrue($from->equals($transition->from));
        $this->assertTrue($to->equals($transition->to));
        $this->assertSame(['tag'], $transition->tags);
    }
}
