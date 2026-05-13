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
        $component = AwaitAllComponent::create('test-component', ['a', 'b']);

        $this->assertInstanceOf(AwaitAllComponent::class, $component);
    }

    #[Test]
    public function componentHasInputPortForEachBranch(): void
    {
        $component = AwaitAllComponent::create('test-component', ['email', 'sms', 'audit']);

        $this->assertInstanceOf(Port::class, $component->input->email);
        $this->assertInstanceOf(Port::class, $component->input->sms);
        $this->assertInstanceOf(Port::class, $component->input->audit);
    }

    #[Test]
    public function componentHasDoneOutputPort(): void
    {
        $component = AwaitAllComponent::create('test-component', ['a', 'b']);

        $this->assertInstanceOf(Port::class, $component->output->done);
    }

    #[Test]
    public function definedNodesContainOneArrivalNodePerBranch(): void
    {
        $component = AwaitAllComponent::create('test-component', ['a', 'b', 'c']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $arrivalNodes = array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode);

        $this->assertCount(3, $arrivalNodes);
    }

    #[Test]
    public function definedNodesContainExactlyOneSyncNode(): void
    {
        $component = AwaitAllComponent::create('test-component', ['a', 'b', 'c']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $syncNodes = array_filter($nodes, static fn($n) => $n instanceof AwaitAllSyncNode);

        $this->assertCount(1, $syncNodes);
    }

    #[Test]
    public function arrivalNodesHaveCorrectBranchNames(): void
    {
        $component = AwaitAllComponent::create('test-component', ['foo', 'bar']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $arrivalNodes = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode));
        $names = array_map(static fn(AwaitAllArrivalNode $n) => $n->branchName, $arrivalNodes);
        sort($names);

        $this->assertSame(['bar', 'foo'], $names);
    }

    #[Test]
    public function transitionCountEqualsNBranchesTimeTwoPlusThree(): void
    {
        $branches = ['a', 'b', 'c'];
        $component = AwaitAllComponent::create('test-component', $branches);
        $this->attachAll($component, $branches);

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        // n*2 (arrival nodes) + 1 (sync to race) + 2 (race condition branches) + 2 (raceWinner => awaitWinner => done)
        $this->assertCount(count($branches) * 2 + 5, $transitions);
    }

    #[Test]
    public function syncTransitionReturnsWaitWhenNoArrivalsRecorded(): void
    {
        $branches = ['a', 'b'];
        $component = AwaitAllComponent::create('test-component', $branches);
        $this->attachAll($component, $branches);

        [$nodes, $transitions] = $component->getDefinedNodesAndTransitions();
        $syncNode = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllSyncNode))[0];
        $syncTransition = array_values(array_filter(
            $transitions,
            static fn($t) => $t->condition !== null && $t->u()->equals($syncNode->id()),
        ))[0];

        $states = $this->makeStates();
        $decision = $syncTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }

    #[Test]
    public function syncTransitionReturnsWaitWhenOnlyPartialArrivalsRecorded(): void
    {
        $branches = ['a', 'b', 'c'];
        $component = AwaitAllComponent::create('test-component', $branches);
        $this->attachAll($component, $branches);

        [$nodes, $transitions] = $component->getDefinedNodesAndTransitions();
        $syncNode = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllSyncNode))[0];
        $syncTransition = array_values(array_filter(
            $transitions,
            static fn($t) => $t->condition !== null && $t->u()->equals($syncNode->id()),
        ))[0];

        $arrivalNodes = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode));

        $states = $this->makeStates();
        $techState = $states->getTechnicalState();
        $states->modifyState($techState->id, [$arrivalNodes[0]->stateName() => true]);

        $decision = $syncTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }

    #[Test]
    public function syncTransitionReturnsAcceptedWhenAllBranchesArrived(): void
    {
        $branches = ['a', 'b', 'c'];
        $component = AwaitAllComponent::create('test-component', $branches);
        $this->attachAll($component, $branches);

        [$nodes, $transitions] = $component->getDefinedNodesAndTransitions();
        $syncNode = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllSyncNode))[0];
        $syncTransition = array_values(array_filter(
            $transitions,
            static fn($t) => $t->condition !== null && $t->u()->equals($syncNode->id()),
        ))[0];

        $arrivalNodes = array_values(array_filter($nodes, static fn($n) => $n instanceof AwaitAllArrivalNode));

        $states = $this->makeStates();
        $techState = $states->getTechnicalState();
        $states->modifyState($techState->id, [
            $arrivalNodes[0]->stateName() => true,
            $arrivalNodes[1]->stateName() => true,
            $arrivalNodes[2]->stateName() => true,
        ]);

        $decision = $syncTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Accepted, $decision);
    }
}
