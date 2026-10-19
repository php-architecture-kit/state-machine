<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Race\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;

/**
 * Entry of one RaceComponent input; its outgoing transition decides between winning and losing.
 */
final class RaceArrivalNode extends PassthroughNode
{
}
