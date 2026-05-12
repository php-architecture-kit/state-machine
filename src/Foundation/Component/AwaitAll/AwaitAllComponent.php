<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll;

use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllArrivalNode;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllSyncNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\AllValidTransitionsStrategy;

class AwaitAllComponent extends Definition
{
    /**
     * Creates an AND-join component that waits for ALL declared input branches to arrive
     * before passing control to the single `done` output.
     *
     * Each branch gets its own named input port backed by an AwaitAllArrivalNode. When a
     * pointer passes through an arrival node, the branch is recorded in the
     * `join-arrived-{componentId}` state. The sync node's outgoing transition then checks
     * that every branch has been recorded before proceeding.
     *
     * @param string[] $branches Names of the input branches (become input port names).
     */
    public static function create(string $uniqueName, array $branches): self
    {
        $syncNode = new AwaitAllSyncNode("state-machine.await-all.sync.{$uniqueName}.sync", []);

        $instance = self::newInstance(
            "state-machine.await-all.{$uniqueName}.wait-for",
            inputs: $branches,
            outputs: ['done'],
        );

        $arrivalStates = [];
        foreach ($branches as $branch) {
            $arrivalNode = new AwaitAllArrivalNode(
                "state-machine.await-all.{$uniqueName}.arrival.{$branch}",
                $branch,
            );

            $instance->addTransition(
                $instance->input->{$branch}, // @phpstan-ignore-line
                $arrivalNode,
                null,
            );

            $instance->addTransition(
                $arrivalNode,
                $syncNode,
                static fn(States $states): TransitionConditionDecision => $states->getTechnicalState()[$arrivalNode->stateName()] !== null
                    ? TransitionConditionDecision::Accepted
                    : TransitionConditionDecision::Wait,
            );

            $arrivalStates[$branch] = $arrivalNode->stateName();
        }

        $instance->addTransition(
            $syncNode,
            $instance->output->done, // @phpstan-ignore-line
            static function (States $states) use ($arrivalStates): TransitionConditionDecision {
                $state = $states->getTechnicalState();

                foreach ($arrivalStates as $branch => $stateName) {
                    if ($state[$stateName] === null) {
                        return TransitionConditionDecision::Wait;
                    }
                }

                return TransitionConditionDecision::Accepted;
            },
        );

        return $instance;
    }
}
