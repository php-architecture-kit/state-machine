<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Join;

use InvalidArgumentException;
use PhpArchitecture\StateMachine\Foundation\Component\Join\Node\JoinArrivalNode;
use PhpArchitecture\StateMachine\Foundation\Component\Shared\TechnicalCounters;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

/**
 * JoinComponent - AND-join: continues once every input has a pointer, then starts over.
 *
 * Ports:
 *   - input: one per declared input
 *   - output: done
 *
 * Pointers wait in the arrival node of their input. A round takes one pointer from every input:
 * the pointer that completes it leaves through `done`, the other pointers of the round are removed.
 * Further pointers on an input wait for the next round. The counters live in the technical state and
 * return to zero after every round, so the component can be re-entered through a cycle and the
 * Execution can be persisted at any point.
 */
class JoinComponent extends Definition
{
    /**
     * @param string[] $inputs names of the joined inputs (become input port names), at least two
     */
    public static function create(string $uniqueName, array $inputs): self
    {
        if (count($inputs) < 2 || count(array_unique($inputs)) !== count($inputs)) {
            throw new InvalidArgumentException('JoinComponent needs at least two distinct inputs.');
        }

        $baseName = "state-machine.join.{$uniqueName}";
        $instance = self::newInstance($baseName, inputs: $inputs, outputs: ['done']);

        $waiting = [];
        $consumed = [];
        $arrivals = [];
        foreach ($inputs as $input) {
            $arrivals[$input] = new JoinArrivalNode("{$baseName}.arrival.{$input}");
            $waiting[$input] = $arrivals[$input]->waitingCounter();
            $consumed[$input] = $arrivals[$input]->consumedCounter();
        }

        foreach ($arrivals as $input => $arrival) {
            $instance->addTransition($instance->input->{$input}, $arrival);
            $instance->addTransition(
                $arrival,
                $instance->output->done,
                static function (States $states) use ($input, $waiting, $consumed): TransitionConditionDecision {
                    if (TechnicalCounters::get($states, $consumed[$input]) > 0) {
                        TechnicalCounters::change($states, [$consumed[$input] => -1]);

                        return TransitionConditionDecision::Rejected;
                    }

                    foreach ($waiting as $counter) {
                        if (TechnicalCounters::get($states, $counter) < 1) {
                            return TransitionConditionDecision::Wait;
                        }
                    }

                    $changes = array_fill_keys($waiting, -1);
                    foreach ($consumed as $other => $counter) {
                        if ($other !== $input) {
                            $changes[$counter] = 1;
                        }
                    }
                    TechnicalCounters::change($states, $changes);

                    return TransitionConditionDecision::Accepted;
                },
            );
        }

        return $instance;
    }
}
