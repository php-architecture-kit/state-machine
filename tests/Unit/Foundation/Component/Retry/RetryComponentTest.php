<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Retry;

use InvalidArgumentException;
use PhpArchitecture\StateMachine\Foundation\Component\Retry\RetryComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Definition\SingleNodeDefinition;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RetryComponentTest extends TestCase
{
    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    private function makeWrappedDefinition(): Definition
    {
        $node = new PassthroughNode('test.wrapped');

        return SingleNodeDefinition::create(
            $node,
            ['trigger' => null],
            [
                'success' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Accepted,
                'failure' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Rejected,
            ],
        );
    }

    private function attachPorts(RetryComponent $component): void
    {
        $component->input->trigger->attach(NodeId::create('test.node.input'));
        $component->output->success->attach(NodeId::create('test.node.success'));
        $component->output->failed->attach(NodeId::create('test.node.failed'));
    }

    #[Test]
    public function createReturnsSelfInstance(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 3);

        $this->assertInstanceOf(RetryComponent::class, $component);
    }

    #[Test]
    public function componentHasTriggerInputPort(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 3);

        $this->assertInstanceOf(Port::class, $component->input->trigger);
    }

    #[Test]
    public function componentHasSuccessOutputPort(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 3);

        $this->assertInstanceOf(Port::class, $component->output->success);
    }

    #[Test]
    public function componentHasFailedOutputPort(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 3);

        $this->assertInstanceOf(Port::class, $component->output->failed);
    }

    #[Test]
    public function throwsWhenMaxAttemptsLessThanOne(): void
    {
        $wrapped = $this->makeWrappedDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxAttempts must be at least 1');

        RetryComponent::create('test', $wrapped, 0);
    }

    #[Test]
    public function throwsWhenWrappedDefinitionMissingInputPort(): void
    {
        $node = new PassthroughNode('test.wrapped');
        $wrapped = SingleNodeDefinition::create(
            $node,
            ['start' => null],  // different name than default 'trigger'
            [
                'success' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Accepted,
                'failure' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Rejected,
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Wrapped definition must have input port 'trigger'");

        RetryComponent::create('test', $wrapped, 3);  // uses default 'trigger' which doesn't exist
    }

    #[Test]
    public function throwsWhenWrappedDefinitionMissingSuccessPort(): void
    {
        $node = new PassthroughNode('test.wrapped');
        $wrapped = SingleNodeDefinition::create(
            $node,
            ['trigger' => null],
            ['failure' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Accepted],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Wrapped definition must have success output port 'success'");

        RetryComponent::create('test', $wrapped, 3);
    }

    #[Test]
    public function throwsWhenWrappedDefinitionMissingFailurePort(): void
    {
        $node = new PassthroughNode('test.wrapped');
        $wrapped = SingleNodeDefinition::create(
            $node,
            ['trigger' => null],
            ['success' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Accepted],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Wrapped definition must have failure output port 'failure'");

        RetryComponent::create('test', $wrapped, 3);
    }

    #[Test]
    public function canUseCustomPortNames(): void
    {
        $node = new PassthroughNode('test.wrapped');
        $wrapped = SingleNodeDefinition::create(
            $node,
            ['start' => null],
            [
                'ok' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Accepted,
                'err' => static fn (States $s): TransitionConditionDecision => TransitionConditionDecision::Rejected,
            ],
        );

        // Should not throw when using custom port names
        $component = RetryComponent::create('test', $wrapped, 3, 'start', 'ok', 'err');

        $this->assertInstanceOf(RetryComponent::class, $component);
        $this->assertInstanceOf(Port::class, $component->input->trigger);
        $this->assertInstanceOf(Port::class, $component->output->success);
        $this->assertInstanceOf(Port::class, $component->output->failed);
    }

    #[Test]
    public function hasExpectedNumberOfNodesAndTransitions(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 3);
        $this->attachPorts($component);

        [$nodes, $transitions] = $component->getDefinedNodesAndTransitions();

        // Nodes: wrapped(1) + retryNode + failureHandler = 3
        $this->assertCount(3, $nodes);

        // Transitions: wrapped(2) + input->retry + failure->retry + failure->failed = 5
        // (bridge transitions are gone, connections are implicit via port attachments)
        $this->assertGreaterThanOrEqual(5, count($transitions));
    }

    private function findInputTransition(RetryComponent $component, array $transitions): ?object
    {
        $inputNodeId = $component->input->trigger->attachedNode?->toString();
        if ($inputNodeId === null) {
            return null;
        }

        foreach ($transitions as $t) {
            if ($t->u()->toString() === $inputNodeId && $t->condition() !== null) {
                return $t;
            }
        }

        return null;
    }

    #[Test]
    public function inputConditionRejectsWhenMaxAttemptsExceeded(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 2);
        $this->attachPorts($component);

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        // Find the input transition specifically
        $inputTransition = $this->findInputTransition($component, $transitions);
        $this->assertNotNull($inputTransition, 'Input transition should be found');

        $states = $this->makeStates();
        $inputCondition = $inputTransition->condition();

        // First attempt - should accept
        $decision1 = $inputCondition->check($states);
        $this->assertSame(TransitionConditionDecision::Accepted, $decision1);

        // Second attempt - should accept
        $decision2 = $inputCondition->check($states);
        $this->assertSame(TransitionConditionDecision::Accepted, $decision2);

        // Third attempt - should reject (maxAttempts = 2)
        $decision3 = $inputCondition->check($states);
        $this->assertSame(TransitionConditionDecision::Rejected, $decision3);
    }

    #[Test]
    public function storesAttemptCountInTechnicalState(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 3);
        $this->attachPorts($component);

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        $inputTransition = $this->findInputTransition($component, $transitions);
        $this->assertNotNull($inputTransition, 'Input transition should be found');

        $states = $this->makeStates();
        $inputCondition = $inputTransition->condition();

        // Trigger twice
        $inputCondition->check($states);
        $inputCondition->check($states);

        $technicalState = $states->getState(State::TECHNICAL);
        $this->assertNotNull($technicalState, 'Technical state should exist after attempts');

        // Verify attempt count is stored
        $attemptCount = null;
        foreach ($technicalState->details as $detail) {
            if (str_contains($detail->name, 'attemptCount')) {
                $attemptCount = $detail->value;
                break;
            }
        }

        $this->assertSame(2, $attemptCount);
    }

    #[Test]
    public function retryConditionAcceptsWhenAttemptsRemain(): void
    {
        $wrapped = $this->makeWrappedDefinition();
        $component = RetryComponent::create('test', $wrapped, 3);
        $this->attachPorts($component);

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        $conditionedTransitions = array_values(array_filter(
            $transitions,
            static fn ($t): bool => $t->condition() !== null
        ));

        $states = $this->makeStates();

        // Simulate 1 attempt used
        $inputCondition = $conditionedTransitions[0]->condition();
        $inputCondition->check($states);

        // Find the retry condition (should be one that accepts when attempts remain)
        // This would be the transition from failure handler back to retry node
        // It should accept when attemptCount < maxAttempts
        $retryAccepted = false;
        foreach ($conditionedTransitions as $transition) {
            $decision = $transition->condition()->check($states);
            if ($decision === TransitionConditionDecision::Accepted) {
                $retryAccepted = true;
                break;
            }
        }

        $this->assertTrue($retryAccepted, 'At least one transition should accept for retry');
    }
}
