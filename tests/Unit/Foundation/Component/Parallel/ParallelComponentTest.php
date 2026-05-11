<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Parallel;

use PhpArchitecture\StateMachine\Foundation\Component\Fork\ForkComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Fork\Node\ForkNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ParallelComponentTest extends TestCase
{
    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    /**
     * @param string[] $outputNames
     * @return TransitionInterface[]
     */
    private function extractTransitions(ForkComponent $component, array $outputNames): array
    {
        $component->input->trigger->attach(NodeId::create("state-machine.unit.foundation.component.parallel.parallelcompone.node1"));
        foreach ($outputNames as $name) {
            $component->output->{$name}->attach(NodeId::create("state-machine.unit.foundation.component.parallel.parallelcompone.node2"));
        }

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        return array_values($transitions);
    }

    #[Test]
    public function createReturnsSelfInstance(): void
    {
        $component = ForkComponent::create(['a', 'b']);

        $this->assertInstanceOf(ForkComponent::class, $component);
    }

    #[Test]
    public function componentHasTriggerInputPort(): void
    {
        $component = ForkComponent::create(['a', 'b']);

        $this->assertInstanceOf(Port::class, $component->input->trigger);
    }

    #[Test]
    public function componentHasOutputPortForEachBranch(): void
    {
        $component = ForkComponent::create(['email', 'sms', 'audit']);

        $this->assertInstanceOf(Port::class, $component->output->email);
        $this->assertInstanceOf(Port::class, $component->output->sms);
        $this->assertInstanceOf(Port::class, $component->output->audit);
    }

    #[Test]
    public function definedNodesContainParallelNode(): void
    {
        $component = ForkComponent::create(['a', 'b']);
        [$nodes] = $component->getDefinedNodesAndTransitions();

        $hasForkNode = false;
        foreach ($nodes as $node) {
            if ($node instanceof ForkNode) {
                $hasForkNode = true;
                break;
            }
        }

        $this->assertTrue($hasForkNode, 'ForkNode must be present in defined nodes.');
    }

    #[Test]
    public function transitionCountEqualsBranchesPlusOne(): void
    {
        $component = ForkComponent::create(['a', 'b', 'c']);
        $transitions = $this->extractTransitions($component, ['a', 'b', 'c']);

        $this->assertCount(4, $transitions);
    }

    #[Test]
    public function allTransitionsHaveNoCondition(): void
    {
        $component = ForkComponent::create(['a', 'b']);
        $transitions = $this->extractTransitions($component, ['a', 'b']);

        foreach ($transitions as $transition) {
            $this->assertNull($transition->condition);
        }
    }

    #[Test]
    public function singleBranchIsSupported(): void
    {
        $component = ForkComponent::create(['only']);
        $transitions = $this->extractTransitions($component, ['only']);

        $this->assertCount(2, $transitions);
    }

    #[Test]
    public function branchWithoutConditionHasNullCondition(): void
    {
        $component = ForkComponent::create(['a', 'b'], ['a' => fn($s) => true]);
        $transitions = $this->extractTransitions($component, ['a', 'b']);

        $conditioned = array_filter($transitions, fn($t) => $t->condition !== null);
        $unconditioned = array_filter($transitions, fn($t) => $t->condition === null);

        $this->assertCount(1, $conditioned);
        $this->assertCount(2, $unconditioned);
    }

    #[Test]
    public function conditionPredicateYieldsAcceptedWhenTrue(): void
    {
        $component = ForkComponent::create(
            ['a', 'b'],
            ['b' => fn(States $s): bool => true],
        );
        $transitions = $this->extractTransitions($component, ['a', 'b']);

        $conditioned = array_values(array_filter($transitions, fn($t) => $t->condition !== null));
        $decision = $conditioned[0]->condition->check($this->makeStates());

        $this->assertSame(TransitionConditionDecision::Accepted, $decision);
    }

    #[Test]
    public function conditionPredicateYieldsRejectedWhenFalse(): void
    {
        $component = ForkComponent::create(
            ['a', 'b'],
            ['b' => fn(States $s): bool => false],
        );
        $transitions = $this->extractTransitions($component, ['a', 'b']);

        $conditioned = array_values(array_filter($transitions, fn($t) => $t->condition !== null));
        $decision = $conditioned[0]->condition->check($this->makeStates());

        $this->assertSame(TransitionConditionDecision::Rejected, $decision);
    }

    #[Test]
    public function allBranchesCanHaveConditions(): void
    {
        $component = ForkComponent::create(
            ['a', 'b', 'c'],
            [
                'a' => fn(States $s): bool => true,
                'b' => fn(States $s): bool => false,
                'c' => fn(States $s): bool => true,
            ],
        );
        $transitions = $this->extractTransitions($component, ['a', 'b', 'c']);

        $conditioned = array_filter($transitions, fn($t) => $t->condition !== null);

        $this->assertCount(3, $conditioned);
    }
}
