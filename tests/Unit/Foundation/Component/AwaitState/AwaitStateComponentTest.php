<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\AwaitState;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class AwaitStateComponentTest extends TestCase
{
    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    private function makeClockAt(DateTimeImmutable $now): ClockInterface
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($now);
        return $clock;
    }

    /**
     * @return array{TransitionInterface, TransitionInterface, TransitionInterface}
     */
    private function extractTransitions(Definition $component): array
    {
        $component->input->at->attach(NodeId::create("state-machine.unit.foundation.component.awaitstate.awaitstatecom.node1"));
        $component->output->run->attach(NodeId::create("state-machine.unit.foundation.component.awaitstate.awaitstatecom.node2"));
        $component->output->expired->attach(NodeId::create("state-machine.unit.foundation.component.awaitstate.awaitstatecom.node3"));

        [, $transitions] = $component->getDefinedNodesAndTransitions();
        $list = array_values($transitions);

        return [$list[0], $list[1], $list[2]];
    }

    #[Test]
    public function createReturnsSelfInstance(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');

        $this->assertInstanceOf(Definition::class, $component);
    }

    #[Test]
    public function componentHasTriggerInputPort(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');

        $this->assertInstanceOf(Port::class, $component->input->at);
    }

    #[Test]
    public function componentHasDoneAndExpiredOutputPorts(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');

        $this->assertInstanceOf(Port::class, $component->output->run);
        $this->assertInstanceOf(Port::class, $component->output->expired);
    }

    #[Test]
    public function definedNodesContainAwaitStateNode(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $hasPassthroughNode = false;
        foreach ($nodes as $node) {
            if ($node instanceof PassthroughNode) {
                $hasPassthroughNode = true;
                break;
            }
        }

        $this->assertTrue($hasPassthroughNode, 'PassthroughNode must be present in defined nodes.');
    }

    #[Test]
    public function threeTransitionsAreDefinedInComponent(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');
        $component->input->at->attach(NodeId::create("state-machine.unit.foundation.component.awaitstate.awaitstatecom.node4"));
        $component->output->run->attach(NodeId::create("state-machine.unit.foundation.component.awaitstate.awaitstatecom.node5"));
        $component->output->expired->attach(NodeId::create("state-machine.unit.foundation.component.awaitstate.awaitstatecom.node6"));

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        $this->assertCount(3, $transitions);
    }

    #[Test]
    public function doneTransitionReturnsWaitWhenStateAbsent(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');
        [, $doneTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();

        $decision = $doneTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }

    #[Test]
    public function doneTransitionReturnsAcceptedWhenStatePresent(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');
        [, $doneTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();
        $states->defineState('my_state', []);

        $decision = $doneTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Accepted, $decision);
    }

    #[Test]
    public function doneTransitionReturnsWaitWhenStateExistsButRequiredDetailMissing(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state', 'answer');
        [, $doneTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();
        $states->defineState('my_state', []);

        $decision = $doneTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Wait, $decision);
    }

    #[Test]
    public function doneTransitionReturnsAcceptedWhenStateAndRequiredDetailPresent(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state', 'answer');
        [, $doneTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();
        $states->defineState('my_state', [new StateDetail('answer', 'yes')]);

        $decision = $doneTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Accepted, $decision);
    }

    #[Test]
    public function expiredTransitionReturnsRejectedWhenNotExpired(): void
    {
        $now = new DateTimeImmutable('2025-01-01 12:00:00', new DateTimeZone('UTC'));
        $clock = $this->makeClockAt($now);
        $component = AwaitStateComponent::create('my_state', 'my_state', null, 60, $clock);
        [,, $expiredTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();

        $decision = $expiredTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Rejected, $decision);
    }

    #[Test]
    public function expiredTransitionReturnsAcceptedAfterTimeoutElapsed(): void
    {
        $start = new DateTimeImmutable('2025-01-01 12:00:00', new DateTimeZone('UTC'));
        $afterTimeout = $start->add(new DateInterval('PT61S'));

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturnOnConsecutiveCalls($start, $afterTimeout, $afterTimeout);

        $component = AwaitStateComponent::create('my_state', 'my_state', null, 60, $clock);
        [,, $expiredTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();

        $expiredTransition->condition->check($states);
        $decision = $expiredTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Accepted, $decision);
    }

    #[Test]
    public function doneTransitionReturnsRejectedAfterTimeoutElapsed(): void
    {
        $start = new DateTimeImmutable('2025-01-01 12:00:00', new DateTimeZone('UTC'));
        $afterTimeout = $start->add(new DateInterval('PT61S'));

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturnOnConsecutiveCalls($start, $afterTimeout, $afterTimeout);

        $component = AwaitStateComponent::create('my_state', 'my_state', null, 60, $clock);
        [, $doneTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();
        $states->defineState('my_state', []);

        $doneTransition->condition->check($states);
        $decision = $doneTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Rejected, $decision);
    }

    #[Test]
    public function expiredTransitionReturnsRejectedWhenNoTimeoutConfigured(): void
    {
        $component = AwaitStateComponent::create('my_state', 'my_state');
        [,, $expiredTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();

        $decision = $expiredTransition->condition->check($states);

        $this->assertSame(TransitionConditionDecision::Rejected, $decision);
    }

    #[Test]
    public function expirationStateIsCreatedOnlyOnceEvenIfConditionCalledMultipleTimes(): void
    {
        $now = new DateTimeImmutable('2025-01-01 12:00:00', new DateTimeZone('UTC'));
        $clock = $this->makeClockAt($now);
        $component = AwaitStateComponent::create('my_state', 'my_state', null, 60, $clock);
        [,, $expiredTransition] = $this->extractTransitions($component);
        $states = $this->makeStates();

        $expiredTransition->condition->check($states);
        $expiredTransition->condition->check($states);

        $stateCount = count(array_filter(
            $states->states,
            static fn($s) => $s->name === State::TECHNICAL,
        ));
        $this->assertSame(1, $stateCount);
    }
}
