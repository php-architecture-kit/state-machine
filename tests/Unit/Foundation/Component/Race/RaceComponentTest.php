<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Race;

use InvalidArgumentException;
use PhpArchitecture\StateMachine\Foundation\Component\Race\Node\RaceArrivalNode;
use PhpArchitecture\StateMachine\Foundation\Component\Race\RaceComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\DefinitionCompiler;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RaceComponentTest extends TestCase
{
    #[Test]
    public function hasAnInputPortPerInputAndAWinnerOutput(): void
    {
        $race = RaceComponent::create('unit', ['answer', 'timeout']);

        $this->assertInstanceOf(Port::class, $race->input->answer);
        $this->assertInstanceOf(Port::class, $race->input->timeout);
        $this->assertInstanceOf(Port::class, $race->output->winner);
    }

    #[Test]
    public function compilesToOneArrivalNodePerInput(): void
    {
        [$nodes] = (new DefinitionCompiler())->compile($this->attached(RaceComponent::create('unit', ['a', 'b', 'c'])));

        $names = array_map(static fn($node): string => $node->name(), array_values(array_filter(
            $nodes,
            static fn($node): bool => $node instanceof RaceArrivalNode,
        )));
        sort($names);

        $this->assertSame([
            'state-machine.race.unit.arrival.a',
            'state-machine.race.unit.arrival.b',
            'state-machine.race.unit.arrival.c',
        ], $names);
    }

    #[Test]
    public function firstArrivalWinsAndEveryOtherInputOwesOneLoser(): void
    {
        $decide = $this->decisions(RaceComponent::create('unit', ['a', 'b', 'c']));
        $states = States::create(ExecutionId::new(), null, null, null);

        $this->assertSame(TransitionConditionDecision::Accepted, $decide['a']($states));
        $this->assertSame(TransitionConditionDecision::Rejected, $decide['b']($states));
        $this->assertSame(TransitionConditionDecision::Rejected, $decide['c']($states));
        // the round is complete, the next arrival starts a new one
        $this->assertSame(TransitionConditionDecision::Accepted, $decide['c']($states));
        $this->assertSame(TransitionConditionDecision::Rejected, $decide['a']($states));
        $this->assertSame(TransitionConditionDecision::Rejected, $decide['b']($states));
    }

    #[Test]
    public function countersAreRemovedWhenTheRoundIsComplete(): void
    {
        $decide = $this->decisions(RaceComponent::create('unit', ['a', 'b']));
        $states = States::create(ExecutionId::new(), null, null, null);

        $decide['a']($states);
        $this->assertCount(1, $states->getTechnicalState()->details);

        $decide['b']($states);
        $this->assertSame([], $states->getTechnicalState()->details);
    }

    #[Test]
    public function rejectsFewerThanTwoInputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RaceComponent::create('unit', ['only']);
    }

    #[Test]
    public function rejectsDuplicateInputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RaceComponent::create('unit', ['a', 'a']);
    }

    private function attached(RaceComponent $race): RaceComponent
    {
        foreach ((array) $race->input as $name => $port) {
            $port->attach(NodeId::create("state-machine.unit.race.before.{$name}"));
        }
        $race->output->winner->attach(NodeId::create('state-machine.unit.race.after'));

        return $race;
    }

    /** @return array<string,callable(States):TransitionConditionDecision> input name => decision of its arrival */
    private function decisions(RaceComponent $race): array
    {
        [, $transitions] = (new DefinitionCompiler())->compile($this->attached($race));

        $decisions = [];
        foreach ($transitions as $transition) {
            assert($transition instanceof Transition);
            if ($transition->condition === null) {
                continue;
            }
            foreach ((array) $race->input as $name => $port) {
                if ($transition->input->equals(NodeId::create("state-machine.race.unit.arrival.{$name}"))) {
                    $decisions[$name] = $transition->condition->check(...);
                }
            }
        }

        return $decisions;
    }
}
