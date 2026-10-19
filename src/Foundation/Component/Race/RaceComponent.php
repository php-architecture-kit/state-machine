<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Race;

use InvalidArgumentException;
use PhpArchitecture\StateMachine\Foundation\Component\Race\Node\RaceArrivalNode;
use PhpArchitecture\StateMachine\Foundation\Component\Shared\TechnicalCounters;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

/**
 * RaceComponent - the first pointer to arrive wins, one pointer from every other input loses.
 *
 * Ports:
 *   - input: one per declared input
 *   - output: winner
 *
 * A round is one arrival per input. The winner leaves through `winner` at once and every other
 * input owes one loser; a pointer arriving on an input that owes a loser is removed. The counters
 * live in the technical state and return to zero once all losers arrived, so the component can be
 * re-entered through a cycle and the Execution can be persisted at any point between rounds.
 */
class RaceComponent extends Definition
{
    /**
     * @param string[] $inputs names of the competing inputs (become input port names), at least two
     */
    public static function create(string $uniqueName, array $inputs): self
    {
        if (count($inputs) < 2 || count(array_unique($inputs)) !== count($inputs)) {
            throw new InvalidArgumentException('RaceComponent needs at least two distinct inputs.');
        }

        $baseName = "state-machine.race.{$uniqueName}";
        $instance = self::newInstance($baseName, inputs: $inputs, outputs: ['winner']);

        $losers = [];
        $arrivals = [];
        foreach ($inputs as $input) {
            $arrivals[$input] = new RaceArrivalNode("{$baseName}.arrival.{$input}");
            $losers[$input] = $arrivals[$input]->id->toString() . '.losers';
        }

        foreach ($arrivals as $input => $arrival) {
            $instance->addTransition($instance->input->{$input}, $arrival);
            $instance->addTransition(
                $arrival,
                $instance->output->winner,
                static function (States $states) use ($input, $losers): TransitionConditionDecision {
                    if (TechnicalCounters::get($states, $losers[$input]) > 0) {
                        TechnicalCounters::change($states, [$losers[$input] => -1]);

                        return TransitionConditionDecision::Rejected;
                    }

                    $owed = $losers;
                    unset($owed[$input]);
                    TechnicalCounters::change($states, array_fill_keys($owed, 1));

                    return TransitionConditionDecision::Accepted;
                },
            );
        }

        return $instance;
    }
}
