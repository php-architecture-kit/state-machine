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
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node1"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node2"));

        $this->assertInstanceOf(TransitionId::class, $transition->id);
    }

    #[Test]
    public function createReturnsDifferentIdOnEachCall(): void
    {
        $input = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node3");
        $output = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node4");

        $a = Transition::create($input, $output);
        $b = Transition::create($input, $output);

        $this->assertFalse($a->id->equals($b->id));
    }

    #[Test]
    public function createStoresInputAndOutputNodes(): void
    {
        $input = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node5");
        $output = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node6");

        $transition = Transition::create($input, $output);

        $this->assertTrue($input->equals($transition->input));
        $this->assertTrue($output->equals($transition->output));
    }

    #[Test]
    public function createStoresNullConditionByDefault(): void
    {
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node7"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node8"));

        $this->assertNull($transition->condition);
    }

    #[Test]
    public function createStoresEmptyTagsByDefault(): void
    {
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node9"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node10"));

        $this->assertSame([], $transition->tags);
    }

    #[Test]
    public function createStoresTags(): void
    {
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node11"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node12"), null, ['priority', 'fast']);

        $this->assertSame(['priority', 'fast'], $transition->tags);
    }

    #[Test]
    public function constructorThrowsInvalidTransitionExceptionOnNonStringTag(): void
    {
        $this->expectException(InvalidTransitionException::class);

        Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node13"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node14"), null, [42]);
    }

    #[Test]
    public function uReturnsInputNode(): void
    {
        $input = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node15");

        $transition = Transition::create($input, NodeId::create("state-machine.unit.foundation.transition.transitiontest.node16"));

        $this->assertTrue($input->equals($transition->u()));
    }

    #[Test]
    public function vReturnsOutputNode(): void
    {
        $output = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node17");

        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node18"), $output);

        $this->assertTrue($output->equals($transition->v()));
    }

    #[Test]
    public function tagsReturnsProvidedTags(): void
    {
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node19"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node20"), null, ['tag1']);

        $this->assertSame(['tag1'], $transition->tags());
    }

    #[Test]
    public function typeReturnsDirectedEdgeType(): void
    {
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node21"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node22"));

        $this->assertSame(EdgeType::Directed, $transition->type());
    }

    #[Test]
    public function idMethodReturnsTransitionId(): void
    {
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.transitiontest.node23"), NodeId::create("state-machine.unit.foundation.transition.transitiontest.node24"));

        $this->assertSame($transition->id, $transition->id());
    }

    #[Test]
    public function recreateUsesProvidedId(): void
    {
        $id = TransitionId::new();
        $input = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node25");
        $output = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node26");

        $transition = Transition::recreate($id, $input, $output, null, []);

        $this->assertTrue($id->equals($transition->id));
    }

    #[Test]
    public function recreatePreservesInputOutputAndTags(): void
    {
        $input = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node27");
        $output = NodeId::create("state-machine.unit.foundation.transition.transitiontest.node28");

        $transition = Transition::recreate(TransitionId::new(), $input, $output, null, ['tag']);

        $this->assertTrue($input->equals($transition->input));
        $this->assertTrue($output->equals($transition->output));
        $this->assertSame(['tag'], $transition->tags);
    }
}
