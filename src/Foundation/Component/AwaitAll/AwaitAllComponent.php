<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll;

use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllArrivalNode;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllSyncNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

class AwaitAllComponent extends Definition
{
    /**
     * Creates a join (AND-join) component that waits for all declared branches to arrive
     * before passing control to the single output.
     *
     * Each branch gets its own input port backed by a dedicated ArrivalNode. When a pointer
     * reaches an ArrivalNode its handler records the branch name in States. The SyncNode then
     * checks whether every expected branch has been recorded and either waits or proceeds.
     *
     * Usage:
     *   $join = AwaitAllComponent::create(['notifyEmail', 'notifySms', 'logAudit']);
     *
     *   $machine->addDefinition($join);
     *
     *   $machine->addTransition($emailNode->id,  $join->input->notifyEmail);
     *   $machine->addTransition($smsNode->id,    $join->input->notifySms);
     *   $machine->addTransition($auditNode->id,  $join->input->logAudit);
     *   $machine->addTransition($join->output->done, $nextNode->id);
     *
     * @param string[] $branches  Names of the parallel input branches to synchronize.
     */
    public static function create(array $branches): self
    {
        $instance = self::newInstance(
            inputs: $branches,
            outputs: ['done'],
        );

        $componentId = 'join-' . uniqid('', true);
        $syncNode = new AwaitAllSyncNode();

        foreach ($branches as $branch) {
            $arrivalNode = new AwaitAllArrivalNode($componentId, $branch);

            $instance->addTransition(
                $instance->input->{$branch},
                $arrivalNode,
                null,
            );

            $instance->addTransition(
                $arrivalNode,
                $syncNode,
                null,
            );
        }

        $instance->addTransition(
            $syncNode,
            $instance->output->done,
            static function (States $states) use ($componentId, $branches): TransitionConditionDecision {
                $state = $states->getState('join-arrived-' . $componentId);

                if ($state === null) {
                    return TransitionConditionDecision::Wait;
                }

                foreach ($branches as $branch) {
                    if (!isset($state->details[$branch])) {
                        return TransitionConditionDecision::Wait;
                    }
                }

                return TransitionConditionDecision::Accepted;
            },
        );

        return $instance;
    }
}
