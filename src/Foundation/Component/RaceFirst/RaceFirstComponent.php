<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\RaceFirst;

use PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\Node\RaceConditionNode;
use PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\Node\RaceFirstTerminalNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;

/**
 * RaceFirstComponent - first pointer to arrive wins and continues,
 * all subsequent pointers are terminated.
 *
 * Ports:
 *   - input: gateway (single input)
 *   - output: winner (single output)
 *
 * Losers are automatically routed to an internal terminal node
 * and removed by TerminalNodeStrategy.
 */
class RaceFirstComponent extends Definition
{
    /**
     * Creates a race-first component where the first pointer to arrive wins
     * and continues to the output, while all subsequent pointers are terminated.
     *
     * @param string $uniqueName Unique identifier for this component instance
     */
    public static function create(string $uniqueName): self
    {
        $baseName = "state-machine.race-first.{$uniqueName}";

        $raceConditionNode = new RaceConditionNode("{$baseName}.condition-check", [], new FirstValidTransitionStrategy());
        $terminalNode = new RaceFirstTerminalNode("{$baseName}.terminal");

        $instance = self::newInstance(
            "{$baseName}.gateway",
            inputs: ['gateway'],
            outputs: ['winner'],
        );

        // gateway -> raceConditionNode (unconditional)
        $instance->addTransition(
            $instance->input->gateway, // @phpstan-ignore-line
            $raceConditionNode,
            null,
        );

        // raceConditionNode -> winner (if this pointer is the first winner)
        $instance->addTransition(
            $raceConditionNode,
            $instance->output->winner, // @phpstan-ignore-line
            static function (States $states) use ($raceConditionNode): TransitionConditionDecision {
                $state = $states->getTechnicalState();
                $winnerState = $state[$raceConditionNode->stateName()] ?? null;

                // If no winner yet, this transition is available
                // (actual winner recording happens in handler)
                if ($winnerState === null) {
                    return TransitionConditionDecision::Accepted;
                }

                return TransitionConditionDecision::Rejected;
            },
        );

        // raceConditionNode -> terminal (if there's already a winner)
        $instance->addTransition(
            $raceConditionNode,
            $terminalNode,
            static function (States $states) use ($raceConditionNode): TransitionConditionDecision {
                $state = $states->getTechnicalState();
                $winnerState = $state[$raceConditionNode->stateName()] ?? null;

                // If there's already a winner, this pointer is a loser -> terminal
                if ($winnerState !== null) {
                    return TransitionConditionDecision::Accepted;
                }

                return TransitionConditionDecision::Rejected;
            },
        );

        return $instance;
    }
}
