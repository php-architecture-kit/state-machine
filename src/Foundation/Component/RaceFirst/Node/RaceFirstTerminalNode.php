<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;

/**
 * Internal terminal node for RaceFirstComponent.
 * All losers are routed here and automatically removed by TerminalNodeStrategy.
 */
final class RaceFirstTerminalNode extends PassthroughNode
{
}
