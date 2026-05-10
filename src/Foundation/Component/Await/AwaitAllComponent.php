<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Await;

use DateInterval;
use DateTimeZone;
use PhpArchitecture\Clock\LocalizedClock;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Definition\SingleNodeDefinition;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use Psr\Clock\ClockInterface;

class AwaitAllComponent extends Definition
{
    /**
     * Creates an AND-join component that waits for ALL declared input branches to arrive
     * before passing control to the single `run` output. If a timeout is given, the
     * `expired` output fires instead when the deadline is exceeded.
     *
     * Each branch gets its own named input port. When a pointer transitions through an
     * input port its arrival is recorded in the technical state. The sync condition then
     * checks that every branch has been recorded before proceeding.
     *
     * Once the sync fires (either `run` or `expired`), a consumed flag prevents any
     * late-arriving branch pointers from re-triggering the output; those pointers are
     * cleanly discarded by the engine's RejectStrategy.
     *
     * @param array<string,null|TransitionCondition|callable(States):TransitionConditionDecision> $inputs
     */
    public static function create(
        string $uniqueName,
        array $inputs,
        ?int $timeout = null,
        ?ClockInterface $clock = null
    ): Definition {
        $node = new PassthroughNode("state-machine.await-all.{$uniqueName}");
        $nodeId = $node->id;
        $clock ??= new LocalizedClock(new DateTimeZone('UTC'));
        $branchNames = array_keys($inputs);

        $wrappedInputs = [];
        foreach ($inputs as $branchName => $userCondition) {
            $wrappedInputs[$branchName] = static function (States $states) use ($nodeId, $branchName, $userCondition): TransitionConditionDecision {
                if ($userCondition !== null) {
                    $decision = is_callable($userCondition) ? $userCondition($states) : $userCondition->check($states);
                    if ($decision !== TransitionConditionDecision::Accepted) {
                        return $decision;
                    }
                }

                self::recordArrival($states, $nodeId, $branchName);

                return TransitionConditionDecision::Accepted;
            };
        }

        $instance = SingleNodeDefinition::create(
            $node,
            $wrappedInputs,
            [
                'run' => static function (States $states) use ($nodeId, $branchNames, $timeout, $clock): TransitionConditionDecision {
                    if (self::isConsumed($states, $nodeId)) {
                        return TransitionConditionDecision::Rejected;
                    }

                    self::createExpirationState($states, $nodeId, $timeout, $clock);

                    if (self::isExpired($states, $nodeId, $clock)) {
                        return TransitionConditionDecision::Rejected;
                    }

                    foreach ($branchNames as $branchName) {
                        if (!self::hasArrived($states, $nodeId, $branchName)) {
                            return TransitionConditionDecision::Wait;
                        }
                    }

                    self::markConsumed($states, $nodeId);

                    return TransitionConditionDecision::Accepted;
                },
                'expired' => static function (States $states) use ($nodeId, $timeout, $clock): TransitionConditionDecision {
                    if (self::isConsumed($states, $nodeId)) {
                        return TransitionConditionDecision::Rejected;
                    }

                    self::createExpirationState($states, $nodeId, $timeout, $clock);

                    if (self::isExpired($states, $nodeId, $clock)) {
                        self::markConsumed($states, $nodeId);

                        return TransitionConditionDecision::Accepted;
                    }

                    return TransitionConditionDecision::Rejected;
                },
            ],
        );

        return $instance;
    }

    private static function recordArrival(States $states, NodeId $nodeId, string $branchName): void
    {
        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            $state = $states->defineState(State::TECHNICAL, []);
        }

        $key = $nodeId->toString() . ".arrived.{$branchName}";
        if ($state[$key] === null) {
            $states->modifyState($state->id, [$key => true]);
        }
    }

    private static function hasArrived(States $states, NodeId $nodeId, string $branchName): bool
    {
        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            return false;
        }

        return $state[$nodeId->toString() . ".arrived.{$branchName}"] !== null;
    }

    private static function markConsumed(States $states, NodeId $nodeId): void
    {
        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            $state = $states->defineState(State::TECHNICAL, []);
        }

        if ($state[$nodeId->toString() . ".consumed"] === null) {
            $states->modifyState($state->id, [$nodeId->toString() . ".consumed" => true]);
        }
    }

    private static function isConsumed(States $states, NodeId $nodeId): bool
    {
        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            return false;
        }

        return $state[$nodeId->toString() . ".consumed"] !== null;
    }

    private static function createExpirationState(States $states, NodeId $nodeId, ?int $timeout, ClockInterface $clock): void
    {
        if ($timeout === null) {
            return;
        }

        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            $state = $states->defineState(State::TECHNICAL, []);
        }

        $expiresAt = $state[$nodeId->toString() . ".expiresAt"] ?? null;
        if ($expiresAt === null) {
            $states->modifyState($state->id, [$nodeId->toString() . ".expiresAt" => $clock->now()->add(new DateInterval('PT' . $timeout . 'S'))]);
        }
    }

    private static function isExpired(States $states, NodeId $nodeId, ClockInterface $clock): bool
    {
        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            return false;
        }

        $expiresAt = $state[$nodeId->toString() . ".expiresAt"] ?? null;
        if ($expiresAt === null) {
            return false;
        }

        return $clock->now() > $expiresAt->value;
    }
}
