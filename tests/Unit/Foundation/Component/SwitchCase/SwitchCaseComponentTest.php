<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\SwitchCase;

use PhpArchitecture\StateMachine\Foundation\Component\SwitchCase\SwitchCaseComponent;
use PhpArchitecture\StateMachine\Foundation\Component\SwitchCase\Node\SwitchCaseNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SwitchCaseComponentTest extends TestCase
{
    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    /**
     * Attaches dummy nodes to all ports so getDefinedNodesAndTransitions() resolves transitions.
     *
     * @param string[] $outputNames
     * @return TransitionInterface[]
     */
    private function extractTransitions(SwitchCaseComponent $component, array $outputNames): array
    {
        $component->input->trigger->attach(NodeId::new());
        foreach ($outputNames as $name) {
            $component->output->{$name}->attach(NodeId::new());
        }

        [, $transitions] = $component->getDefinedNodesAndTransitions();

        return array_values($transitions);
    }

    #[Test]
    public function createReturnsSelfInstance(): void
    {
        $component = SwitchCaseComponent::create([
            'yes' => static fn(States $s): bool => true,
            'no'  => static fn(States $s): bool => false,
        ]);

        $this->assertInstanceOf(SwitchCaseComponent::class, $component);
    }

    #[Test]
    public function componentHasTriggerInputPort(): void
    {
        $component = SwitchCaseComponent::create([
            'branch' => static fn(States $s): bool => true,
        ]);

        $this->assertInstanceOf(Port::class, $component->input->trigger);
    }

    #[Test]
    public function componentHasOutputPortForEachBranch(): void
    {
        $component = SwitchCaseComponent::create([
            'approved' => static fn(States $s): bool => true,
            'rejected' => static fn(States $s): bool => false,
        ]);

        $this->assertInstanceOf(Port::class, $component->output->approved);
        $this->assertInstanceOf(Port::class, $component->output->rejected);
    }

    #[Test]
    public function definedNodesContainSwitchCaseNode(): void
    {
        $component = SwitchCaseComponent::create([
            'branch' => static fn(States $s): bool => true,
        ]);

        [$nodes] = $component->getDefinedNodesAndTransitions();

        $hasNode = false;
        foreach ($nodes as $node) {
            if ($node instanceof SwitchCaseNode) {
                $hasNode = true;
                break;
            }
        }

        $this->assertTrue($hasNode, 'SwitchCaseNode must be present in defined nodes.');
    }

    #[Test]
    public function transitionCountEqualsNumberOfBranchesPlusOne(): void
    {
        $component = SwitchCaseComponent::create([
            'a' => static fn(States $s): bool => true,
            'b' => static fn(States $s): bool => false,
            'c' => static fn(States $s): bool => false,
        ]);

        $transitions = $this->extractTransitions($component, ['a', 'b', 'c']);

        $this->assertCount(4, $transitions);
    }

    #[Test]
    public function branchTransitionReturnsAcceptedWhenPredicateIsTrue(): void
    {
        $component = SwitchCaseComponent::create([
            'yes' => static fn(States $s): bool => true,
            'no'  => static fn(States $s): bool => false,
        ]);

        $transitions = $this->extractTransitions($component, ['yes', 'no']);
        $states = $this->makeStates();

        $yesTransition = $transitions[1];

        $this->assertSame(TransitionConditionDecision::Accepted, $yesTransition->condition->check($states));
    }

    #[Test]
    public function branchTransitionReturnsRejectedWhenPredicateIsFalse(): void
    {
        $component = SwitchCaseComponent::create([
            'yes' => static fn(States $s): bool => true,
            'no'  => static fn(States $s): bool => false,
        ]);

        $transitions = $this->extractTransitions($component, ['yes', 'no']);
        $states = $this->makeStates();

        $noTransition = $transitions[2];

        $this->assertSame(TransitionConditionDecision::Rejected, $noTransition->condition->check($states));
    }

    #[Test]
    public function predicateReceivesStatesObject(): void
    {
        $capturedStates = null;
        $component = SwitchCaseComponent::create([
            'branch' => static function (States $s) use (&$capturedStates): bool {
                $capturedStates = $s;
                return true;
            },
        ]);

        $transitions = $this->extractTransitions($component, ['branch']);
        $states = $this->makeStates();
        $transitions[1]->condition->check($states);

        $this->assertSame($states, $capturedStates);
    }

    #[Test]
    public function firstTriggerTransitionHasNoCondition(): void
    {
        $component = SwitchCaseComponent::create([
            'branch' => static fn(States $s): bool => true,
        ]);

        $transitions = $this->extractTransitions($component, ['branch']);

        $this->assertNull($transitions[0]->condition);
    }

    #[Test]
    public function branchOrderIsPreserved(): void
    {
        $order = [];
        $component = SwitchCaseComponent::create([
            'first'  => static function (States $s) use (&$order): bool { $order[] = 'first';  return false; },
            'second' => static function (States $s) use (&$order): bool { $order[] = 'second'; return false; },
            'third'  => static function (States $s) use (&$order): bool { $order[] = 'third';  return true;  },
        ]);

        $transitions = $this->extractTransitions($component, ['first', 'second', 'third']);
        $states = $this->makeStates();

        foreach (array_slice($transitions, 1) as $transition) {
            $transition->condition->check($states);
        }

        $this->assertSame(['first', 'second', 'third'], $order);
    }
}
