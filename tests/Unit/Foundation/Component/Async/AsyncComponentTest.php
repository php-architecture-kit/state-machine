<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Async;

use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncNode;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AsyncComponentTest extends TestCase
{
    private function makeTask(): Task
    {
        return new class implements Task {};
    }

    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    private function attachPorts(AsyncComponent $component): void
    {
        $component->input->trigger->attach(NodeId::create("state-machine.unit.foundation.component.async.asynccomponenttest.node1"));
        $component->output->done->attach(NodeId::create("state-machine.unit.foundation.component.async.asynccomponenttest.node2"));
        $component->output->expired->attach(NodeId::create("state-machine.unit.foundation.component.async.asynccomponenttest.node3"));
    }

    #[Test]
    public function createReturnsSelfInstance(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());

        $this->assertInstanceOf(AsyncComponent::class, $component);
    }

    #[Test]
    public function componentHasTriggerInputPort(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());

        $this->assertInstanceOf(Port::class, $component->input->trigger);
    }

    #[Test]
    public function componentHasDoneOutputPort(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());

        $this->assertInstanceOf(Port::class, $component->output->done);
    }

    #[Test]
    public function componentHasExpiredOutputPort(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());

        $this->assertInstanceOf(Port::class, $component->output->expired);
    }

    #[Test]
    public function definedNodesContainAsyncNode(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $asyncNodes = array_filter($nodes, fn($n) => $n instanceof AsyncNode);
        $this->assertCount(1, $asyncNodes);
    }

    #[Test]
    public function definedNodesContainPassthroughNode(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $passthroughNodes = array_filter($nodes, fn($n) => $n instanceof PassthroughNode);
        $this->assertCount(1, $passthroughNodes);
    }

    #[Test]
    public function asyncNodeCarriesStateName(): void
    {
        $component = AsyncComponent::create('payment_result', fn($s) => $this->makeTask());
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $asyncNode = array_values(array_filter($nodes, fn($n) => $n instanceof AsyncNode))[0];
        $this->assertSame('payment_result', $asyncNode->stateName);
    }

    #[Test]
    public function transitionCountIsFour(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());
        $this->attachPorts($component);

        [, $transitions] = $component->getDefinedNodesAndTransitions();
        $this->assertCount(4, $transitions);
    }

    #[Test]
    public function awaitTransitionReturnsWaitWhenStateAbsent(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());
        $this->attachPorts($component);

        [, $transitions] = $component->getDefinedNodesAndTransitions();
        $conditioned = array_values(array_filter($transitions, fn($t) => $t->condition !== null));

        $decision = $conditioned[0]->condition->check($this->makeStates());
        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }

    #[Test]
    public function awaitTransitionReturnsAcceptedWhenStatePresent(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask());
        $this->attachPorts($component);

        [, $transitions] = $component->getDefinedNodesAndTransitions();
        $conditioned = array_values(array_filter($transitions, fn($t) => $t->condition !== null));

        $states = $this->makeStates();
        $states->defineState('result', []);

        $decision = $conditioned[0]->condition->check($states);
        $this->assertSame(TransitionConditionDecision::Accepted, $decision);
    }

    #[Test]
    public function awaitTransitionReturnsWaitWhenDetailNameMissing(): void
    {
        $component = AsyncComponent::create('result', fn($s) => $this->makeTask(), 'value');
        $this->attachPorts($component);

        [, $transitions] = $component->getDefinedNodesAndTransitions();
        $conditioned = array_values(array_filter($transitions, fn($t) => $t->condition !== null));

        $states = $this->makeStates();
        $states->defineState('result', []);

        $decision = $conditioned[0]->condition->check($states);
        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }
}
