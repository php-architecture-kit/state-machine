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
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use Psr\Clock\ClockInterface;

class AwaitStateComponent extends Definition
{
    public static function create(
        string $uniqueName,
        string $stateName,
        ?string $detailName = null,
        ?int $timeout = null,
        ?ClockInterface $clock = null
    ): Definition {
        $node = new PassthroughNode("state-machine.await.{$uniqueName}.{$stateName}");
        $nodeId = $node->id;
        $clock ??= new LocalizedClock(new DateTimeZone('UTC'));

        $instance = SingleNodeDefinition::create(
            $node,
            ['at' => null],
            [
                'run' => static function (States $states) use ($nodeId, $stateName, $detailName, $timeout, $clock): TransitionConditionDecision {
                    self::createExpirationState($states, $nodeId, $timeout, $clock);

                    if (self::isExpired($states, $nodeId, $clock)) {
                        return TransitionConditionDecision::Rejected;
                    }

                    $state = $states->getState($stateName);

                    if ($state === null) {
                        return TransitionConditionDecision::Wait;
                    }

                    if ($detailName === null || isset($state[$detailName])) {
                        return TransitionConditionDecision::Accepted;
                    }

                    return TransitionConditionDecision::Wait;
                },
                'expired' => static function (States $states) use ($nodeId, $timeout, $clock): TransitionConditionDecision {
                    self::createExpirationState($states, $nodeId, $timeout, $clock);
                    if (self::isExpired($states, $nodeId, $clock)) {
                        return TransitionConditionDecision::Accepted;
                    }

                    return TransitionConditionDecision::Rejected;
                }
            ],
        );

        return $instance;
    }

    private static function createExpirationState(States $states, NodeId $nodeId, ?int $timeout, ClockInterface $clock): void
    {
        if ($timeout === null) {
            return;
        }

        $state = $states->getTechnicalState();

        $stateName = self::expiredAtStateName($nodeId);
        if ($state[$stateName] === null) {
            $states->modifyState($state->id, [$stateName => $clock->now()->add(new DateInterval('PT' . $timeout . 'S'))]);
        }
    }

    private static function isExpired(States $states, NodeId $nodeId, ClockInterface $clock): bool
    {
        $state = $states->getTechnicalState();
        $expiresAt = $state[self::expiredAtStateName($nodeId)];
        if ($expiresAt === null) {
            return false;
        }

        return $clock->now() > $expiresAt->value;
    }

    private static function expiredAtStateName(NodeId $nodeId): string
    {
        return $nodeId->toString() . ".expiresAt";
    }
}
