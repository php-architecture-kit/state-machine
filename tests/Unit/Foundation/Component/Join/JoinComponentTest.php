<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Join;

use InvalidArgumentException;
use PhpArchitecture\StateMachine\Foundation\Component\Join\JoinComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Join\Node\JoinArrivalNode;
use PhpArchitecture\StateMachine\Foundation\Component\Shared\TechnicalCounters;
use PhpArchitecture\StateMachine\Foundation\Definition\DefinitionCompiler;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JoinComponentTest extends TestCase
{
    #[Test]
    public function hasAnInputPortPerInputAndADoneOutput(): void
    {
        $join = JoinComponent::create('unit', ['email', 'sms']);

        $this->assertInstanceOf(Port::class, $join->input->email);
        $this->assertInstanceOf(Port::class, $join->input->sms);
        $this->assertInstanceOf(Port::class, $join->output->done);
    }

    #[Test]
    public function compilesToOneArrivalNodePerInput(): void
    {
        [$nodes] = (new DefinitionCompiler())->compile($this->attached(JoinComponent::create('unit', ['a', 'b', 'c'])));

        $names = array_map(static fn($node): string => $node->name(), array_values(array_filter(
            $nodes,
            static fn($node): bool => $node instanceof JoinArrivalNode,
        )));
        sort($names);

        $this->assertSame([
            'state-machine.join.unit.arrival.a',
            'state-machine.join.unit.arrival.b',
            'state-machine.join.unit.arrival.c',
        ], $names);
    }

    #[Test]
    public function waitsUntilEveryInputHasAPointer(): void
    {
        $decide = $this->decisions(JoinComponent::create('unit', ['a', 'b', 'c']));
        $states = $this->states();

        $this->arrive($states, 'a');
        $this->assertSame(TransitionConditionDecision::Wait, $decide['a']($states));
        $this->arrive($states, 'b');
        $this->assertSame(TransitionConditionDecision::Wait, $decide['b']($states));
        $this->assertSame(TransitionConditionDecision::Wait, $decide['a']($states));

        $this->arrive($states, 'c');
        $this->assertSame(TransitionConditionDecision::Accepted, $decide['c']($states));
    }

    #[Test]
    public function otherPointersOfAReleasedRoundAreRemovedAndTheCountersReturnToZero(): void
    {
        $decide = $this->decisions(JoinComponent::create('unit', ['a', 'b']));
        $states = $this->states();

        $this->arrive($states, 'a');
        $this->arrive($states, 'b');
        $this->assertSame(TransitionConditionDecision::Accepted, $decide['b']($states));
        $this->assertSame(TransitionConditionDecision::Rejected, $decide['a']($states));

        $this->assertSame([], $states->getTechnicalState()->details);
    }

    #[Test]
    public function waitingDecisionHasNoSideEffects(): void
    {
        $decide = $this->decisions(JoinComponent::create('unit', ['a', 'b']));
        $states = $this->states();
        $this->arrive($states, 'a');
        $before = $states->getTechnicalState()->details;

        $decide['a']($states);
        $decide['a']($states);

        $this->assertEquals($before, $states->getTechnicalState()->details);
    }

    #[Test]
    public function rejectsFewerThanTwoInputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JoinComponent::create('unit', ['only']);
    }

    #[Test]
    public function rejectsDuplicateInputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JoinComponent::create('unit', ['a', 'a']);
    }

    /** What JoinArrivalNodeHandler does when a pointer enters the input. */
    private function arrive(States $states, string $input): void
    {
        TechnicalCounters::change($states, [(new JoinArrivalNode("state-machine.join.unit.arrival.{$input}"))->waitingCounter() => 1]);
    }

    private function states(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    private function attached(JoinComponent $join): JoinComponent
    {
        foreach ((array) $join->input as $name => $port) {
            $port->attach(NodeId::create("state-machine.unit.join.before.{$name}"));
        }
        $join->output->done->attach(NodeId::create('state-machine.unit.join.after'));

        return $join;
    }

    /** @return array<string,callable(States):TransitionConditionDecision> input name => decision of its arrival */
    private function decisions(JoinComponent $join): array
    {
        [, $transitions] = (new DefinitionCompiler())->compile($this->attached($join));

        $decisions = [];
        foreach ($transitions as $transition) {
            assert($transition instanceof Transition);
            if ($transition->condition === null) {
                continue;
            }
            foreach ((array) $join->input as $name => $port) {
                if ($transition->input->equals(NodeId::create("state-machine.join.unit.arrival.{$name}"))) {
                    $decisions[$name] = $transition->condition->check(...);
                }
            }
        }

        return $decisions;
    }
}
