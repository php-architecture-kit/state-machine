<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitState;

use DateInterval;
use DateTimeZone;
use PhpArchitecture\Clock\LocalizedClock;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitState\Node\AwaitStateNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use Psr\Clock\ClockInterface;

class AwaitStateComponent extends Definition
{
    public static function create(string $stateName, ?string $detailName = null, ?int $timeout = null, ?ClockInterface $clock = null): self
    {
        $instance = self::newInstance(
            inputs: ['trigger'],
            outputs: ['done', 'expired'],
        );

        $clock ??= new LocalizedClock(new DateTimeZone('UTC'));
        $awaitNode = new AwaitStateNode();
        $componentId = 'awaitstate-' . $awaitNode->id->toString();

        $instance->addTransition(
            $instance->input->trigger,
            $awaitNode,
            null,
        );

        $instance->addTransition(
            $awaitNode,
            $instance->output->done,
            function (States $states) use ($componentId, $stateName, $detailName, $timeout, $clock): TransitionConditionDecision {
                self::createExpirationState($states, $componentId, $timeout, $clock);
                if (self::isExpired($states, $componentId, $clock)) {
                    return TransitionConditionDecision::Rejected;
                }

                $state = $states->getState($stateName);

                if ($state === null) {
                    return TransitionConditionDecision::Wait;
                }

                if ($detailName === null || isset($state->details[$detailName])) {
                    return TransitionConditionDecision::Accepted;
                }

                return TransitionConditionDecision::Wait;
            },
        );

        $instance->addTransition(
            $awaitNode,
            $instance->output->expired,
            function (States $states) use ($componentId, $timeout, $clock): TransitionConditionDecision {
                self::createExpirationState($states, $componentId, $timeout, $clock);
                if (self::isExpired($states, $componentId, $clock)) {
                    return TransitionConditionDecision::Accepted;
                }

                return TransitionConditionDecision::Rejected;
            },
        );

        return $instance;
    }

    private static function createExpirationState(States $states, string $componentId, ?int $timeout, ClockInterface $clock): void
    {
        if ($timeout === null) {
            return;
        }

        $state = $states->getState($componentId);
        if ($state === null) {
            $states->defineState($componentId, [new StateDetail('expiresAt', $clock->now()->add(new DateInterval('PT' . $timeout . 'S')))]);
        }
    }

    private static function isExpired(States $states, string $componentId, ClockInterface $clock): bool
    {
        $state = $states->getState($componentId);
        if ($state === null) {
            return false;
        }

        return $clock->now() > $state->details['expiresAt']->value;
    }
}
