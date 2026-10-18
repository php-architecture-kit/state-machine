<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Await\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use Psr\Clock\ClockInterface;

/**
 * Waiting node of AwaitStateComponent with a timeout.
 *
 * The expiration moment belongs to a single visit: AwaitStateNodeHandler issues it
 * every time a pointer enters the node, so a cycle leading back here starts a fresh window.
 */
final class AwaitStateNode extends PassthroughNode
{
    public function __construct(
        string $uniqueName,
        public readonly int $timeout,
        public readonly ClockInterface $clock,
    ) {
        parent::__construct($uniqueName);
    }

    public function handlerClass(): string
    {
        return AwaitStateNodeHandler::class;
    }

    public function expiresAtStateName(): string
    {
        return $this->id->toString() . '.expiresAt';
    }
}
