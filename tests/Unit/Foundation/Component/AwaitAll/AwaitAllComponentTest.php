<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\AwaitAll;

use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\AwaitAllComponent;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllArrivalNode;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllSyncNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AwaitAllComponentTest extends TestCase
{
    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    private function attachAll(AwaitAllComponent $component, array $branches): void
    {
        foreach ($branches as $branch) {
            $component->input->{$branch}->attach(NodeId::create("state-machine.unit.foundation.component.awaitall.awaitallcompone.node1"));
        }
        $component->output->done->attach(NodeId::create("state-machine.unit.foundation.component.awaitall.awaitallcompone.node2"));
    }

    #[Test]
    public function createReturnsSelfInstance(): void
    {
        $component = AwaitAllComponent::create(['a', 'b']);

        $this->assertInstanceOf(AwaitAllComponent::class, $component);
    }

    #[Test]
    public function componentHasInputPortForEachBranch(): void
    {
        $component = AwaitAllComponent::create(['email', 'sms', 'audit']);

        $this->assertInstanceOf(Port::class, $component->input->email);
        $this->assertInstanceOf(Port::class, $component->input->sms);
        $this->assertInstanceOf(Port::class, $component->input->audit);
    }

    #[Test]
    public function componentHasDoneOutputPort(): void
    {
        $component = AwaitAllComponent::create(['a', 'b']);

        $this->assertInstanceOf(Port::class, $component->output->done);
    }

    #[Test]
    public function definedNodesContainOneArrivalNodePerBranch(): void
    {
        $component = AwaitAllComponent::create(['a', 'b', 'c']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $arrivalNodes = array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode);

        $this->assertCount(3, $arrivalNodes);
    }

    #[Test]
    public function definedNodesContainExactlyOneSyncNode(): void
    {
        $component = AwaitAllComponent::create(['a', 'b', 'c']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $syncNodes = array_filter($nodes, static fn($n) => $n instanceof AwaitAllSyncNode);

        $this->assertCount(1, $syncNodes);
    }

    #[Test]
    public function arrivalNodesHaveCorrectBranchNames(): void
    {
        $component = AwaitAllComponent::create(['foo', 'bar']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $arrivalNodes = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode));
        $names = array_map(static fn(AwaitAllArrivalNode $n) => $n->branchName, $arrivalNodes);
        sort($names);

        $this->assertSame(['bar', 'foo'], $names);
    }

    #[Test]
    public function allArrivalNodesShareTheSameComponentId(): void
    {
        $component = AwaitAllComponent::create(['a', 'b', 'c']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $arrivalNodes = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode));
        $componentIds = array_unique(array_map(static fn(AwaitAllArrivalNode $n) => $n->componentId, $arrivalNodes));

        $this->assertCount(1, $componentIds);
    }

    #[Test]
    public function transitionCountEqualsNBranchesTimeTwoPlusOne(): void
    {
        $branches = ['a', 'b', 'c'];
        $component = AwaitAllComponent::create($branches);
        $this->attachAll($component, $branches);

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        $this->assertCount(count($branches) * 2 + 1, $transitions);
    }

    #[Test]
    public function syncTransitionReturnsWaitWhenNoArrivalsRecorded(): void
    {
        $branches = ['a', 'b'];
        $component = AwaitAllComponent::create($branches);
        $this->attachAll($component, $branches);

        [, $transitions] = $component->getDefinedNodesAndTransitions();
        $syncTransition = array_values(array_filter(
            $transitions,
            static fn($t) => $t->condition !== null,
        ))[0];

        $states = $this->makeStates();
        $decision = $syncTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }

    #[Test]
    public function syncTransitionReturnsWaitWhenOnlyPartialArrivalsRecorded(): void
    {
        $branches = ['a', 'b', 'c'];
        $component = AwaitAllComponent::create($branches);
        $this->attachAll($component, $branches);

        [$nodes, $transitions] = $component->getDefinedNodesAndTransitions();
        $syncTransition = array_values(array_filter(
            $transitions,
            static fn($t) => $t->condition !== null,
        ))[0];

        $arrivalNodes = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode));
        $componentId = $arrivalNodes[0]->componentId;

        $states = $this->makeStates();
        $states->defineState('join-arrived-' . $componentId, [
            new \PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail('a', true),
        ]);

        $decision = $syncTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }

    #[Test]
    public function syncTransitionReturnsAcceptedWhenAllBranchesArrived(): void
    {
        $branches = ['a', 'b', 'c'];
        $component = AwaitAllComponent::create($branches);
        $this->attachAll($component, $branches);

        [$nodes, $transitions] = $component->getDefinedNodesAndTransitions();
        $syncTransition = array_values(array_filter(
            $transitions,
            static fn($t) => $t->condition !== null,
        ))[0];

        $arrivalNodes = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode));
        $componentId = $arrivalNodes[0]->componentId;

        $states = $this->makeStates();
        $states->defineState('join-arrived-' . $componentId, [
            new \PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail('a', true),
            new \PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail('b', true),
            new \PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail('c', true),
        ]);

        $decision = $syncTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Accepted, $decision);
    }
}
